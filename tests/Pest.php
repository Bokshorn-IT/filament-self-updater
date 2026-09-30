<?php

declare(strict_types=1);

use BokshornIt\FilamentSelfUpdater\Support\ArchiveInstaller;
use BokshornIt\FilamentSelfUpdater\Support\StateStore;
use BokshornIt\FilamentSelfUpdater\Tests\Fixtures\FakeSource;
use BokshornIt\FilamentSelfUpdater\Tests\TestCase;
use BokshornIt\FilamentSelfUpdater\Updater;

uses(TestCase::class)->in(__DIR__);

/**
 * An updater working on the test's sandbox: "app" is the application root,
 * "state" the state directory. Also bound in the container, so the page and
 * the job use the same one.
 *
 * @param  array<string, mixed>  $options  named Updater arguments to override
 */
function makeUpdater(FakeSource $source, array $options = []): Updater
{
    $updater = new Updater(...array_merge([
        'source' => $source,
        'state' => new StateStore(test()->sandbox('state')),
        'installer' => new ArchiveInstaller(test()->sandbox('app'), ['storage', 'vendor']),
        'workingDirectory' => test()->sandbox('app'),
    ], $options));

    app()->instance(Updater::class, $updater);

    return $updater;
}
