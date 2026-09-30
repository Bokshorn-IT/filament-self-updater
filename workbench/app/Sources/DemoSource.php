<?php

declare(strict_types=1);

namespace Workbench\App\Sources;

use BokshornIt\FilamentSelfUpdater\Contracts\ReleaseSource;
use RuntimeException;

/**
 * Always offers the version after the one the demo has installed, and never
 * downloads anything: the demo must not overwrite the package it runs from.
 */
class DemoSource implements ReleaseSource
{
    public function latestVersion(): ?string
    {
        return 'v2.5.0';
    }

    public function download(string $version, string $target): void
    {
        throw new RuntimeException('The demo does not download releases.');
    }
}
