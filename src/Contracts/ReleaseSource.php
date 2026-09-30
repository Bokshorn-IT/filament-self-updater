<?php

declare(strict_types=1);

namespace BokshornIt\FilamentSelfUpdater\Contracts;

interface ReleaseSource
{
    /**
     * The newest version the source offers, or null when it offers none.
     */
    public function latestVersion(): ?string;

    /**
     * Write the archive of the given version to the target path. The archive
     * is a zip holding the application root, optionally wrapped in a single
     * top-level directory.
     */
    public function download(string $version, string $target): void;
}
