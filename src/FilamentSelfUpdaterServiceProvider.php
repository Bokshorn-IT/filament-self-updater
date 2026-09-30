<?php

declare(strict_types=1);

namespace BokshornIt\FilamentSelfUpdater;

use BokshornIt\FilamentSelfUpdater\Contracts\ReleaseSource;
use BokshornIt\FilamentSelfUpdater\Sources\GitHubSource;
use BokshornIt\FilamentSelfUpdater\Support\ArchiveInstaller;
use BokshornIt\FilamentSelfUpdater\Support\StateStore;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentSelfUpdaterServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-self-updater';

    public static string $viewNamespace = 'filament-self-updater';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasConfigFile()
            ->hasViews(static::$viewNamespace)
            ->hasTranslations();
    }

    public function packageRegistered(): void
    {
        $this->app->bind(ReleaseSource::class, fn (): GitHubSource => new GitHubSource(
            repository: (string) config('filament-self-updater.repository'),
            token: config('filament-self-updater.token'),
            versionsFrom: (string) config('filament-self-updater.versions_from', GitHubSource::FROM_TAGS),
            prereleases: (bool) config('filament-self-updater.prereleases', false),
            timeout: (int) config('filament-self-updater.download_timeout', 300),
        ));

        $this->app->bind(Updater::class, fn ($app): Updater => new Updater(
            source: $app->make(ReleaseSource::class),
            state: new StateStore((string) config('filament-self-updater.storage_path', storage_path('app/self-updater'))),
            installer: new ArchiveInstaller(base_path(), config('filament-self-updater.exclude', [])),
            fallbackVersion: config('filament-self-updater.installed_version'),
            postUpdateCommands: array_values(config('filament-self-updater.post_update', [])),
            workingDirectory: base_path(),
            timeout: (int) config('filament-self-updater.timeout', 1800),
            cacheTtl: (int) config('filament-self-updater.cache_ttl', 1800),
            queueConnection: config('filament-self-updater.queue.connection'),
            queueName: config('filament-self-updater.queue.name'),
        ));
    }
}
