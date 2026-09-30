<?php

declare(strict_types=1);

use BokshornIt\FilamentSelfUpdater\Pages\ApplicationUpdate;
use BokshornIt\FilamentSelfUpdater\SelfUpdaterPlugin;

it('resolves the plugin configured on the current panel', function (): void {
    expect(SelfUpdaterPlugin::resolve())->toBe(SelfUpdaterPlugin::get());
});

it('authorizes nobody until told otherwise', function (): void {
    expect(app(SelfUpdaterPlugin::class)->isAuthorized())->toBeFalse()
        ->and(app(SelfUpdaterPlugin::class)->authorize(true)->isAuthorized())->toBeTrue();
});

it('falls back to config defaults when nothing is configured', function (): void {
    $plugin = app(SelfUpdaterPlugin::class);

    expect($plugin->getPage())->toBe(ApplicationUpdate::class)
        ->and($plugin->getNavigationIcon())->toBe('heroicon-o-cloud-arrow-down')
        ->and($plugin->hasNavigation())->toBeTrue();
});

it('lets settings on the plugin win over the config file', function (): void {
    $plugin = app(SelfUpdaterPlugin::class)->navigationGroup('System')->registerNavigation(false);

    expect($plugin->getNavigationGroup())->toBe('System')
        ->and($plugin->hasNavigation())->toBeFalse();
});
