<?php

declare(strict_types=1);

namespace BokshornIt\FilamentSelfUpdater\Support;

use RuntimeException;

/**
 * The files the updater keeps between runs: the recorded version, the list
 * of files the last update installed, the status of the latest run and a log
 * per run.
 */
class StateStore
{
    public function __construct(
        protected string $path,
    ) {
        $this->path = rtrim($path, '/\\');
    }

    public function version(): ?string
    {
        $version = trim((string) $this->read('version'));

        return $version === '' ? null : $version;
    }

    public function putVersion(string $version): void
    {
        $this->write('version', $version);
    }

    /**
     * @return list<string>
     */
    public function manifest(): array
    {
        $decoded = json_decode((string) $this->read('manifest.json'), true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    /**
     * @param  list<string>  $files
     */
    public function putManifest(array $files): void
    {
        $this->write('manifest.json', (string) json_encode($files, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function status(): ?UpdateStatus
    {
        $decoded = json_decode((string) $this->read('status.json'), true);

        return is_array($decoded) ? UpdateStatus::fromArray($decoded) : null;
    }

    public function putStatus(UpdateStatus $status): void
    {
        $this->write('status.json', (string) json_encode($status->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function appendLog(string $log, string $text): void
    {
        $this->ensureDirectory($this->path.DIRECTORY_SEPARATOR.'logs');

        file_put_contents($this->logPath($log), $text, FILE_APPEND | LOCK_EX);
    }

    /**
     * The last lines of a log, with terminal colour codes removed.
     */
    public function logTail(string $log, int $lines = 200): string
    {
        $path = $this->logPath($log);

        if (! is_file($path)) {
            return '';
        }

        $content = preg_replace('/\e\[[0-9;]*[A-Za-z]/', '', (string) file_get_contents($path)) ?? '';

        return implode("\n", array_slice(explode("\n", rtrim($content)), -$lines));
    }

    public function logPath(string $log): string
    {
        return $this->path.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.basename($log);
    }

    public function workspace(): string
    {
        return $this->path.DIRECTORY_SEPARATOR.'work';
    }

    protected function read(string $file): ?string
    {
        $path = $this->path.DIRECTORY_SEPARATOR.$file;

        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    /**
     * Written to a temporary file and renamed into place, so the page polling
     * the status never reads a half-written file.
     */
    protected function write(string $file, string $contents): void
    {
        $this->ensureDirectory($this->path);

        $path = $this->path.DIRECTORY_SEPARATOR.$file;
        $temporary = $path.'.'.bin2hex(random_bytes(4)).'.tmp';

        if (file_put_contents($temporary, $contents) === false || ! rename($temporary, $path)) {
            @unlink($temporary);

            throw new RuntimeException("The updater state file [{$path}] cannot be written.");
        }
    }

    protected function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("The updater state directory [{$directory}] cannot be created.");
        }
    }
}
