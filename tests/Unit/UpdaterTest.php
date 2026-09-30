<?php

declare(strict_types=1);

use BokshornIt\FilamentSelfUpdater\Enums\UpdateStage;
use BokshornIt\FilamentSelfUpdater\Enums\UpdateState;
use BokshornIt\FilamentSelfUpdater\Events\UpdateFailed;
use BokshornIt\FilamentSelfUpdater\Events\UpdateInstalled;
use BokshornIt\FilamentSelfUpdater\Exceptions\UpdateInProgressException;
use BokshornIt\FilamentSelfUpdater\Jobs\InstallUpdate;
use BokshornIt\FilamentSelfUpdater\Support\StateStore;
use BokshornIt\FilamentSelfUpdater\Support\UpdateStatus;
use BokshornIt\FilamentSelfUpdater\Tests\Fixtures\FakeSource;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;

use function Illuminate\Support\php_binary;

beforeEach(function (): void {
    mkdir($this->sandbox('app'));

    $this->source = new FakeSource([
        'v1.0.0' => ['app/Article.php' => 'v1', 'app/Legacy.php' => 'v1'],
        'v1.1.0' => ['app/Article.php' => 'v1.1', 'update.sh' => 'echo done'],
    ]);
    $this->state = new StateStore($this->sandbox('state'));
});

describe('versions', function (): void {
    it('reads the installed version from the state directory', function (): void {
        $this->state->putVersion('v1.0.0');

        expect(makeUpdater($this->source, ['fallbackVersion' => 'v0.9.0'])->installedVersion())->toBe('v1.0.0');
    });

    it('falls back to the configured version, then to unknown', function (): void {
        expect(makeUpdater($this->source, ['fallbackVersion' => 'v0.9.0'])->installedVersion())->toBe('v0.9.0')
            ->and(makeUpdater($this->source)->installedVersion())->toBeNull();
    });

    it('caches the available version until it is refreshed', function (): void {
        $updater = makeUpdater($this->source);

        $updater->availableVersion();
        $updater->availableVersion();
        expect($this->source->lookups)->toBe(1);

        $updater->refreshAvailableVersion();
        expect($this->source->lookups)->toBe(2);
    });

    it('offers an update when the installed version is older or unknown', function (): void {
        $updater = makeUpdater($this->source);

        expect($updater->isUpdateAvailable())->toBeTrue();

        $this->state->putVersion('v1.0.0');
        expect($updater->isUpdateAvailable())->toBeTrue();

        $this->state->putVersion('1.1.0');
        expect($updater->isUpdateAvailable())->toBeFalse();
    });
});

describe('queueing', function (): void {
    it('records the run as queued and dispatches the job', function (): void {
        Queue::fake();
        $this->state->putVersion('v1.0.0');

        $status = makeUpdater($this->source, ['queueName' => 'deploy'])->queue('v1.1.0');

        expect($status->state)->toBe(UpdateState::Queued)
            ->and($status->from)->toBe('v1.0.0')
            ->and($this->state->status()?->version)->toBe('v1.1.0');

        Queue::assertPushedOn('deploy', InstallUpdate::class, fn (InstallUpdate $job): bool => $job->version === 'v1.1.0');
    });

    it('refuses a second run while one is active', function (): void {
        Queue::fake();
        $updater = makeUpdater($this->source);

        $updater->queue('v1.1.0');
        $updater->queue('v1.1.0');
    })->throws(UpdateInProgressException::class);

    it('does not let an abandoned run block the next one', function (): void {
        Queue::fake();
        $this->state->putStatus(UpdateStatus::queued('v1.1.0', null, 'old.log')->running(UpdateStage::Install));
        $this->travel(31)->minutes();

        $updater = makeUpdater($this->source, ['timeout' => 1800]);

        expect($updater->isRunning())->toBeFalse();

        $updater->queue('v1.1.0');

        Queue::assertPushed(InstallUpdate::class);
    });
});

describe('running', function (): void {
    it('installs the release, prunes dropped files and runs the post-update commands', function (): void {
        Event::fake();
        Process::fake();

        $updater = makeUpdater($this->source, ['postUpdateCommands' => ['composer install --no-dev']]);
        $updater->run('v1.0.0');
        $status = $updater->run('v1.1.0');

        expect($status->state)->toBe(UpdateState::Succeeded)
            ->and(file_get_contents($this->sandbox('app/app/Article.php')))->toBe('v1.1')
            ->and(is_file($this->sandbox('app/app/Legacy.php')))->toBeFalse()
            ->and($this->state->version())->toBe('v1.1.0')
            ->and($this->state->manifest())->toBe(['app/Article.php', 'update.sh'])
            ->and(is_dir($this->state->workspace()))->toBeFalse();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === 'composer install --no-dev'
            && $process->path === $this->sandbox('app'));
        Event::assertDispatched(UpdateInstalled::class, 2);
    });

    it('runs the commands in order and puts the PHP CLI binary in place of @php', function (): void {
        Process::fake();

        makeUpdater($this->source, ['postUpdateCommands' => [
            '@php artisan migrate --force',
            ['@php', 'artisan', 'optimize'],
            ['sh', 'deploy/after-update.sh'],
        ]])->run('v1.0.0');

        Process::assertRanInOrder([
            escapeshellarg(php_binary()).' artisan migrate --force',
            fn (PendingProcess $process): bool => $process->command === [php_binary(), 'artisan', 'optimize'],
            fn (PendingProcess $process): bool => $process->command === ['sh', 'deploy/after-update.sh'],
        ]);
    });

    it('leaves the application untouched when the download fails', function (): void {
        Event::fake();
        $this->state->putVersion('v1.0.0');

        $status = makeUpdater($this->source)->run('v9.9.9');

        expect($status->state)->toBe(UpdateState::Failed)
            ->and($status->stage)->toBe(UpdateStage::Download)
            ->and($status->filesUpdated())->toBeFalse()
            ->and($this->state->version())->toBe('v1.0.0')
            ->and(glob($this->sandbox('app/*')))->toBe([]);

        Event::assertDispatched(UpdateFailed::class);
    });

    it('records the new version but stops at the first post-update command that fails', function (): void {
        Process::fake([
            'composer*' => Process::result(output: 'Installing ...', errorOutput: 'Your lock file does not match', exitCode: 1),
            '*' => Process::result(),
        ]);

        $updater = makeUpdater($this->source, ['postUpdateCommands' => ['composer install', 'php artisan migrate --force']]);
        $status = $updater->run('v1.1.0');

        expect($status->state)->toBe(UpdateState::Failed)
            ->and($status->stage)->toBe(UpdateStage::PostUpdate)
            ->and($status->filesUpdated())->toBeTrue()
            ->and($this->state->version())->toBe('v1.1.0')
            ->and($updater->logTail($status))->toContain('Failed: The post-update command [composer install] exited with code 1.');

        Process::assertRanTimes(fn (PendingProcess $process): bool => true, 1);
    });

    it('writes the log in English whatever the app locale', function (): void {
        app()->setLocale('de');

        $updater = makeUpdater($this->source);
        $status = $updater->run('v1.0.0');

        expect($updater->logTail($status))->toContain('Downloading ...')
            ->and($updater->logTail($status))->not->toContain('Herunterladen');
    });

    it('runs nothing when no post-update commands are configured', function (): void {
        Process::fake();

        $status = makeUpdater($this->source)->run('v1.0.0');

        expect($status->state)->toBe(UpdateState::Succeeded);
        Process::assertNothingRan();
    });

    it('marks a run whose job died as failed', function (): void {
        Queue::fake();
        $updater = makeUpdater($this->source);
        $updater->queue('v1.1.0');

        (new InstallUpdate('v1.1.0'))->failed(new RuntimeException('Job timed out.'));

        expect($updater->status()?->state)->toBe(UpdateState::Failed)
            ->and($updater->status()?->message)->toBe('Job timed out.')
            ->and($updater->isRunning())->toBeFalse();
    });
});
