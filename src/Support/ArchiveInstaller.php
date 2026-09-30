<?php

declare(strict_types=1);

namespace BokshornIt\FilamentSelfUpdater\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use ZipArchive;

/**
 * Unpacks a release archive and lays it over the application root, leaving
 * excluded paths alone.
 */
class ArchiveInstaller
{
    /**
     * @param  list<string>  $exclude  paths relative to the base path
     */
    public function __construct(
        protected string $basePath,
        protected array $exclude = [],
    ) {
        $this->basePath = rtrim($basePath, '/\\');
        $this->exclude = array_values(array_filter(array_map(
            fn (string $path): string => trim(str_replace('\\', '/', $path), '/'),
            $exclude,
        )));
    }

    /**
     * Extract the archive into the workspace and return the directory holding
     * the application root. GitHub wraps a source archive in one top-level
     * directory named after the commit, which is unwrapped here.
     */
    public function extract(string $archive, string $workspace): string
    {
        $zip = new ZipArchive;

        if ($zip->open($archive) !== true) {
            throw new RuntimeException("The update archive [{$archive}] cannot be opened.");
        }

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = str_replace('\\', '/', (string) $zip->getNameIndex($index));

                if (str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name) === 1) {
                    throw new RuntimeException("The update archive contains an unsafe path [{$name}].");
                }
            }

            if (! $zip->extractTo($workspace)) {
                throw new RuntimeException("The update archive [{$archive}] cannot be extracted.");
            }
        } finally {
            $zip->close();
        }

        $entries = array_values(array_diff(scandir($workspace) ?: [], ['.', '..']));

        if (count($entries) === 1 && is_dir($workspace.DIRECTORY_SEPARATOR.$entries[0])) {
            return $workspace.DIRECTORY_SEPARATOR.$entries[0];
        }

        return $workspace;
    }

    /**
     * Copy every file below the source onto the base path and return the
     * relative paths written, sorted.
     *
     * @return list<string>
     */
    public function copy(string $source): array
    {
        $source = rtrim($source, '/\\');
        $written = [];

        /** @var SplFileInfo $item */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)) as $item) {
            if (! $item->isFile() || $item->isLink()) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($source) + 1));

            if ($this->isExcluded($relative)) {
                continue;
            }

            $target = $this->absolute($relative);

            if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0755, true) && ! is_dir(dirname($target))) {
                throw new RuntimeException("The directory for [{$relative}] cannot be created.");
            }

            if (! copy($item->getPathname(), $target)) {
                throw new RuntimeException("The file [{$relative}] cannot be written.");
            }

            $written[] = $relative;
        }

        sort($written);

        return $written;
    }

    /**
     * Delete the files a previous update installed that the new one did not,
     * then any directory that leaves empty. Only paths from the previous
     * list are candidates, so files the application creates itself are never
     * touched.
     *
     * @param  list<string>  $previous
     * @param  list<string>  $installed
     * @return list<string> the relative paths deleted
     */
    public function prune(array $previous, array $installed): array
    {
        $keep = array_flip($installed);
        $deleted = [];

        foreach ($previous as $relative) {
            if (isset($keep[$relative]) || $this->isExcluded($relative) || preg_match('#(^|/)\.\.(/|$)#', $relative) === 1) {
                continue;
            }

            $target = $this->absolute($relative);

            if (is_file($target) && @unlink($target)) {
                $deleted[] = $relative;
                $this->removeEmptyParents(dirname($target));
            }
        }

        return $deleted;
    }

    public function isExcluded(string $relative): bool
    {
        foreach ($this->exclude as $path) {
            if ($relative === $path || str_starts_with($relative, $path.'/')) {
                return true;
            }
        }

        return false;
    }

    protected function removeEmptyParents(string $directory): void
    {
        while (str_starts_with($directory, $this->basePath.DIRECTORY_SEPARATOR) && is_dir($directory)) {
            if ((new FilesystemIterator($directory))->valid() || ! @rmdir($directory)) {
                return;
            }

            $directory = dirname($directory);
        }
    }

    protected function absolute(string $relative): string
    {
        return $this->basePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
}
