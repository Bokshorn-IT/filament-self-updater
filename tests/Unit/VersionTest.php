<?php

declare(strict_types=1);

use BokshornIt\FilamentSelfUpdater\Support\Version;

it('compares versions with and without a leading v', function (): void {
    expect(Version::compare('v1.10.0', '1.9.0'))->toBe(1)
        ->and(Version::compare('v2.0.0', '2.0.0'))->toBe(0)
        ->and(Version::isNewer('v1.0.1', null))->toBeTrue();
});

it('tells stable versions from prereleases and other tags', function (): void {
    expect(Version::isStable('v1.2.3'))->toBeTrue()
        ->and(Version::isStable('v1.2.3-rc.1'))->toBeFalse()
        ->and(Version::isVersion('v1.2.3-rc.1'))->toBeTrue()
        ->and(Version::isVersion('nightly'))->toBeFalse();
});
