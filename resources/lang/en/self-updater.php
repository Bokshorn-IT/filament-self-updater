<?php

declare(strict_types=1);

return [
    'navigation_label' => 'Update',
    'title' => 'Application update',

    'actions' => [
        'install' => 'Install update',
        'install_now' => 'Install now',
        'check' => 'Check for updates',
        'open' => 'Open update',
    ],

    'modal' => [
        'install_description' => 'Version :version is downloaded and installed in the background. The application may be briefly unavailable while it runs.',
    ],

    'notifications' => [
        'up_to_date' => 'The application is up to date.',
        'available' => 'Update available: :version',
        'check_failed' => 'Could not check for updates.',
        'in_progress' => 'An update is already running.',
        'installed' => 'Version :version installed.',
        'failed_before' => 'The update failed. The application was not changed.',
        'failed_after' => 'The files were updated, but a post-update command failed.',
    ],

    'status' => [
        'available' => 'Update available',
        'up_to_date' => 'Up to date',
        'check_failed' => 'Could not check for updates',
        'latest_running' => 'The newest version is installed.',
        'ready' => 'A new version is ready to install.',
        'installed_version' => 'Installed version',
        'latest_version' => 'Newest version',
        'unknown' => 'unknown',
        'badge_current' => 'Current',
        'badge_new' => 'New',
    ],

    'run' => [
        'heading' => 'Latest update',
        'version' => 'Version',
        'queued_at' => 'Started',
        'finished_at' => 'Finished',
        'stage' => 'Step',
        'waiting_for_worker' => 'Waiting for a queue worker to pick up the update.',
        'failed_before' => 'The update stopped before any file was changed.',
        'failed_after' => 'The files are on :version, but a post-update command failed. Check the log below.',
        'show_log' => 'Show log (:lines line)|Show log (:lines lines)',
        'hide_log' => 'Hide log',
        'log_truncated' => 'The first line is only in the log file :file.|The first :lines lines are only in the log file :file.',
    ],

    'state' => [
        'queued' => 'Queued',
        'running' => 'Running',
        'succeeded' => 'Installed',
        'failed' => 'Failed',
        'abandoned' => 'Abandoned',
    ],

    'stage' => [
        'download' => 'Downloading',
        'extract' => 'Extracting',
        'install' => 'Installing files',
        'post_update' => 'Running post-update commands',
    ],

    'widget' => [
        'current' => 'Application up to date',
    ],
];
