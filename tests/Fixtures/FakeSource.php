<?php

declare(strict_types=1);

namespace BokshornIt\FilamentSelfUpdater\Tests\Fixtures;

use BokshornIt\FilamentSelfUpdater\Contracts\ReleaseSource;
use RuntimeException;
use ZipArchive;

/**
 * Offers releases held in memory, each packed like a GitHub source archive.
 */
class FakeSource implements ReleaseSource
{
    public int $lookups = 0;

    public bool $unreachable = false;

    /**
     * @param  array<string, array<string, string>>  $releases  version => [relative path => contents]
     */
    public function __construct(
        public array $releases = [],
    ) {}

    public function latestVersion(): ?string
    {
        $this->lookups++;

        if ($this->unreachable) {
            throw new RuntimeException('GitHub cannot be reached.');
        }

        $versions = array_keys($this->releases);
        usort($versions, 'version_compare');

        return end($versions) ?: null;
    }

    public function download(string $version, string $target): void
    {
        if ($this->unreachable || ! isset($this->releases[$version])) {
            throw new RuntimeException("Version [{$version}] cannot be downloaded.");
        }

        $zip = new ZipArchive;
        $zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($this->releases[$version] as $path => $contents) {
            $zip->addFromString('acme-app-3f2a91c/'.$path, $contents);
        }

        $zip->close();
    }
}
