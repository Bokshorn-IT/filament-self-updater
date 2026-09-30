<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use BokshornIt\FilamentSelfUpdater\Enums\UpdateStage;
use BokshornIt\FilamentSelfUpdater\Enums\UpdateState;
use BokshornIt\FilamentSelfUpdater\Support\StateStore;
use BokshornIt\FilamentSelfUpdater\Support\UpdateStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Workbench\App\Models\User;

/**
 * Leaves the demo on v2.4.0 with v2.5.0 waiting, and the log of the update
 * that brought it to v2.4.0 on the page.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->where('email', 'demo@example.com')->delete();

        User::query()->create([
            'name' => 'Jane Doe',
            'email' => 'demo@example.com',
            'password' => Hash::make('password'),
        ]);

        $path = (string) config('filament-self-updater.storage_path', storage_path('app/self-updater'));
        array_map('unlink', glob($path.'/logs/*.log') ?: []);

        $state = new StateStore($path);
        $state->putVersion('v2.4.0');

        $queuedAt = CarbonImmutable::now()->subDays(3)->setTime(9, 14, 2);
        $log = $queuedAt->format('Y-m-d_His').'_v2.3.2_to_v2.4.0.log';

        $status = new UpdateStatus(
            state: UpdateState::Succeeded,
            version: 'v2.4.0',
            from: 'v2.3.2',
            queuedAt: $queuedAt,
            stage: UpdateStage::PostUpdate,
            startedAt: $queuedAt->addSeconds(1),
            finishedAt: $queuedAt->addSeconds(47),
            log: $log,
        );
        $state->putStatus($status);

        $lines = [
            [0, 'Update to v2.4.0 queued.'],
            [1, 'Downloading ...'],
            [3, 'Extracting ...'],
            [4, 'Installing files ...'],
            [5, '412 files written, 3 removed.'],
            [5, 'Running post-update commands ...'],
        ];

        foreach ($lines as [$offset, $message]) {
            $state->appendLog($log, '['.$queuedAt->addSeconds($offset)->format('Y-m-d H:i:s').'] '.$message."\n");
        }

        $commands = [
            [6, 'composer install --no-interaction --no-dev --optimize-autoloader', [
                'Installing dependencies from lock file',
                'Package operations: 0 installs, 2 updates, 0 removals',
                '  - Upgrading laravel/framework (v13.22.0 => v13.23.0)',
                '  - Upgrading filament/filament (v5.7.2 => v5.7.3)',
                'Generating optimized autoload files',
            ]],
            [41, '@php artisan migrate --force', [
                '   INFO  Running migrations.',
                '  2026_09_20_081500_add_archived_at_to_projects ......... 18.42ms DONE',
            ]],
            [43, '@php artisan optimize', [
                '   INFO  Caching framework bootstrap, configuration, and metadata.',
            ]],
        ];

        foreach ($commands as [$offset, $command, $output]) {
            $state->appendLog($log, '['.$queuedAt->addSeconds($offset)->format('Y-m-d H:i:s').'] $ '.$command."\n");
            $state->appendLog($log, implode("\n", $output)."\n");
        }

        $state->appendLog($log, '['.$queuedAt->addSeconds(47)->format('Y-m-d H:i:s').'] Update to v2.4.0 installed.'."\n");
    }
}
