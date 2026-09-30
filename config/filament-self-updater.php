<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Repository
    |--------------------------------------------------------------------------
    |
    | The GitHub repository the application updates itself from, as
    | "owner/name". A private repository needs a token that can read its
    | contents. Versions come either from the git tags (the highest version
    | wins) or from the published GitHub releases.
    |
    */

    'repository' => env('UPDATER_REPOSITORY'),

    'token' => env('UPDATER_GITHUB_TOKEN'),

    'versions_from' => env('UPDATER_VERSIONS_FROM', 'tags'),

    'prereleases' => (bool) env('UPDATER_PRERELEASES', false),

    /*
    |--------------------------------------------------------------------------
    | Installed version
    |--------------------------------------------------------------------------
    |
    | Every successful update records its version in the state directory. This
    | value is only read while nothing has been recorded yet, which is the
    | case on an application that was deployed some other way. Left empty,
    | the installed version shows as unknown.
    |
    */

    'installed_version' => env('UPDATER_INSTALLED_VERSION'),

    /*
    |--------------------------------------------------------------------------
    | State directory
    |--------------------------------------------------------------------------
    |
    | Holds the recorded version, the list of files the last update installed,
    | the status of the running update and one log file per update. Keep it
    | inside an excluded path, so an update never overwrites it.
    |
    */

    'storage_path' => storage_path('app/self-updater'),

    /*
    |--------------------------------------------------------------------------
    | Excluded paths
    |--------------------------------------------------------------------------
    |
    | Paths relative to the application root that an update never writes to
    | and never deletes from, whatever the release contains.
    |
    */

    'exclude' => [
        '.env',
        '.git',
        'bootstrap/cache',
        'node_modules',
        'public/storage',
        'storage',
        'vendor',
    ],

    /*
    |--------------------------------------------------------------------------
    | Post-update commands
    |--------------------------------------------------------------------------
    |
    | Run one after another from the application root once the new files are
    | in place, and stopped at the first that fails. A string runs through the
    | system shell, an array runs as the program and its arguments without
    | one. "@php" stands for the PHP binary running the update, which is the
    | reliable way to reach artisan on hosts where "php" on the PATH is some
    | other version. Output is written to the update's log.
    |
    */

    'post_update' => [
        // 'composer install --no-interaction --no-dev --optimize-autoloader',
        // '@php artisan migrate --force',
        // '@php artisan optimize',
        // '@php artisan queue:restart',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue and timeouts
    |--------------------------------------------------------------------------
    |
    | The update runs as a queued job. "timeout" caps the whole job including
    | the post-update commands, "download_timeout" the download alone (both in
    | seconds). An update still marked as running after "timeout" counts as
    | abandoned, so a crashed worker cannot block updates for good.
    |
    */

    'queue' => [
        'connection' => env('UPDATER_QUEUE_CONNECTION'),
        'name' => env('UPDATER_QUEUE'),
    ],

    'timeout' => 1800,

    'download_timeout' => 300,

    /*
    |--------------------------------------------------------------------------
    | Available version cache
    |--------------------------------------------------------------------------
    |
    | How long the newest version found on GitHub is cached, in seconds. The
    | "check for updates" action always asks GitHub again.
    |
    */

    'cache_ttl' => 1800,

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    |
    | Where the update page sits in the panel. Anything set on the plugin
    | (->navigationGroup(), ->navigationSort(), ...) wins over these.
    |
    */

    'navigation' => [
        'group' => null,
        'icon' => 'heroicon-o-cloud-arrow-down',
        'sort' => null,
        'enabled' => true,
    ],

];
