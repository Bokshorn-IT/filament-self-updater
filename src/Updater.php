<?php

declare(strict_types=1);

namespace BokshornIt\FilamentSelfUpdater;

use BokshornIt\FilamentSelfUpdater\Contracts\ReleaseSource;
use BokshornIt\FilamentSelfUpdater\Enums\UpdateStage;
use BokshornIt\FilamentSelfUpdater\Events\UpdateFailed;
use BokshornIt\FilamentSelfUpdater\Events\UpdateInstalled;
use BokshornIt\FilamentSelfUpdater\Exceptions\UpdateInProgressException;
use BokshornIt\FilamentSelfUpdater\Jobs\InstallUpdate;
use BokshornIt\FilamentSelfUpdater\Support\ArchiveInstaller;
use BokshornIt\FilamentSelfUpdater\Support\StateStore;
use BokshornIt\FilamentSelfUpdater\Support\UpdateStatus;
use BokshornIt\FilamentSelfUpdater\Support\Version;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

use function Illuminate\Support\php_binary;

use RuntimeException;
use Throwable;

/**
 * Compares the installed version with the newest one the source offers, and
 * installs a version: download, extract, copy over the application, remove
 * the files the release dropped, run the post-update commands.
 */
class Updater
{
    protected const AVAILABLE_CACHE_KEY = 'filament-self-updater:available';

    protected const QUEUE_LOCK = 'filament-self-updater:queue';

    /**
     * @param  list<string|list<string>>  $postUpdateCommands  a string runs through the shell, an array without one
     */
    public function __construct(
        protected ReleaseSource $source,
        protected StateStore $state,
        protected ArchiveInstaller $installer,
        protected ?string $fallbackVersion = null,
        protected array $postUpdateCommands = [],
        protected ?string $workingDirectory = null,
        protected int $timeout = 1800,
        protected int $cacheTtl = 1800,
        protected ?string $queueConnection = null,
        protected ?string $queueName = null,
    ) {}

    /**
     * The version recorded by the last update, else the configured one, else
     * null for unknown.
     */
    public function installedVersion(): ?string
    {
        return $this->state->version() ?? (filled($this->fallbackVersion) ? $this->fallbackVersion : null);
    }

    /**
     * The newest version the source offers, cached. Throws when the source
     * cannot be reached, and a failed lookup is not cached.
     */
    public function availableVersion(): ?string
    {
        $cached = Cache::get(static::AVAILABLE_CACHE_KEY);

        if (is_array($cached) && array_key_exists('version', $cached)) {
            return $cached['version'];
        }

        $version = $this->source->latestVersion();

        Cache::put(static::AVAILABLE_CACHE_KEY, ['version' => $version], $this->cacheTtl);

        return $version;
    }

    public function refreshAvailableVersion(): ?string
    {
        Cache::forget(static::AVAILABLE_CACHE_KEY);

        return $this->availableVersion();
    }

    public function isUpdateAvailable(): bool
    {
        $available = $this->availableVersion();

        return $available !== null && Version::isNewer($available, $this->installedVersion());
    }

    public function status(): ?UpdateStatus
    {
        return $this->state->status();
    }

    public function isRunning(): bool
    {
        return $this->status()?->isActive($this->timeout) ?? false;
    }

    public function logTail(UpdateStatus $status, int $lines = 200): string
    {
        return $status->log === null ? '' : $this->state->logTail($status->log, $lines);
    }

    /**
     * Record the update as queued and dispatch the job running it. Checking
     * for a running update and recording the new one happen under a lock, so
     * two clicks at once cannot both get through.
     *
     * @throws UpdateInProgressException
     */
    public function queue(string $version): UpdateStatus
    {
        return Cache::lock(static::QUEUE_LOCK, 10)->block(5, function () use ($version): UpdateStatus {
            if ($this->isRunning()) {
                throw new UpdateInProgressException('An update is already running.');
            }

            $status = UpdateStatus::queued($version, $this->installedVersion(), $this->logName($version));

            $this->state->putStatus($status);
            $this->state->appendLog((string) $status->log, $this->line("Update to {$version} queued."));

            InstallUpdate::dispatch($version)
                ->onConnection($this->queueConnection)
                ->onQueue($this->queueName);

            return $status;
        });
    }

    /**
     * Install the version. Every outcome ends up in the status and the log
     * rather than being thrown, since this runs on a worker nobody watches.
     */
    public function run(string $version): UpdateStatus
    {
        $status = $this->state->status();

        if ($status === null || $status->version !== $version || $status->state->isFinished()) {
            $status = UpdateStatus::queued($version, $this->installedVersion(), $this->logName($version));
        }

        $workspace = $this->state->workspace();
        $filesystem = new Filesystem;

        try {
            $filesystem->deleteDirectory($workspace);
            $filesystem->ensureDirectoryExists($workspace.DIRECTORY_SEPARATOR.'extracted');

            $status = $this->advance($status, UpdateStage::Download);
            $archive = $workspace.DIRECTORY_SEPARATOR.'release.zip';
            $this->source->download($version, $archive);

            $status = $this->advance($status, UpdateStage::Extract);
            $root = $this->installer->extract($archive, $workspace.DIRECTORY_SEPARATOR.'extracted');

            $status = $this->advance($status, UpdateStage::Install);
            $installed = $this->installer->copy($root);
            $deleted = $this->installer->prune($this->state->manifest(), $installed);
            $this->state->putManifest($installed);
            $this->state->putVersion($version);
            $this->log($status, count($installed).' files written, '.count($deleted).' removed.');

            $status = $this->advance($status, UpdateStage::PostUpdate);
            $this->runPostUpdateCommands($status);

            $status = $status->succeeded();
            $this->state->putStatus($status);
            $this->log($status, "Update to {$version} installed.");

            event(new UpdateInstalled($status));
        } catch (Throwable $exception) {
            $status = $status->failed($exception->getMessage());
            $this->state->putStatus($status);
            $this->log($status, 'Failed: '.$exception->getMessage());

            report($exception);
            event(new UpdateFailed($status, $exception));
        } finally {
            $filesystem->deleteDirectory($workspace);
        }

        return $status;
    }

    /**
     * Marks the run as failed when its job died without finishing, which is
     * what a worker timeout does.
     */
    public function markAbandoned(string $version, string $reason): void
    {
        $status = $this->state->status();

        if ($status === null || $status->version !== $version || $status->state->isFinished()) {
            return;
        }

        $status = $status->failed($reason);
        $this->state->putStatus($status);
        $this->log($status, 'Failed: '.$reason);
    }

    protected function advance(UpdateStatus $status, UpdateStage $stage): UpdateStatus
    {
        $status = $status->running($stage);

        $this->state->putStatus($status);
        $this->log($status, $stage->getLogLine());

        return $status;
    }

    protected function runPostUpdateCommands(UpdateStatus $status): void
    {
        if ($this->postUpdateCommands === []) {
            $this->log($status, 'No post-update commands configured.');

            return;
        }

        foreach ($this->postUpdateCommands as $command) {
            $label = is_array($command) ? implode(' ', $command) : $command;
            $this->log($status, '$ '.$label);

            $result = Process::path($this->workingDirectory ?? base_path())
                ->timeout($this->timeout)
                ->run($this->resolveCommand($command), function (string $type, string $output) use ($status): void {
                    $this->state->appendLog((string) $status->log, $output);
                });

            if ($result->failed()) {
                throw new RuntimeException("The post-update command [{$label}] exited with code {$result->exitCode()}.");
            }
        }
    }

    /**
     * Replaces a leading "@php" with the PHP CLI binary. PHP_BINARY is not
     * that binary when the update runs inside a PHP-FPM request.
     *
     * @param  string|list<string>  $command
     * @return string|list<string>
     */
    protected function resolveCommand(string|array $command): string|array
    {
        if (is_array($command)) {
            if (($command[0] ?? null) === '@php') {
                $command[0] = php_binary();
            }

            return $command;
        }

        if (str_starts_with($command, '@php ')) {
            return escapeshellarg(php_binary()).substr($command, 4);
        }

        return $command;
    }

    protected function log(UpdateStatus $status, string $message): void
    {
        if ($status->log !== null) {
            $this->state->appendLog($status->log, $this->line($message));
        }
    }

    protected function line(string $message): string
    {
        return '['.now()->format('Y-m-d H:i:s').'] '.$message."\n";
    }

    protected function logName(string $version): string
    {
        $safe = fn (?string $value): string => preg_replace('/[^0-9A-Za-z.-]+/', '-', $value ?? 'unknown') ?? 'unknown';

        return now()->format('Y-m-d_His').'_'.$safe($this->installedVersion()).'_to_'.$safe($version).'.log';
    }
}
