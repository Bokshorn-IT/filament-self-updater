<?php

declare(strict_types=1);

namespace BokshornIt\FilamentSelfUpdater\Support;

/**
 * Compares version strings with or without a leading "v", so "v2.1.0" and
 * "2.1.0" are the same version.
 */
final class Version
{
    public static function isVersion(string $value): bool
    {
        return preg_match('/^v?\d+(\.\d+)*([-+][0-9A-Za-z.-]+)?$/i', $value) === 1;
    }

    public static function isStable(string $value): bool
    {
        return preg_match('/^v?\d+(\.\d+)*$/i', $value) === 1;
    }

    public static function compare(string $a, string $b): int
    {
        return version_compare(self::normalize($a), self::normalize($b));
    }

    public static function isNewer(string $candidate, ?string $than): bool
    {
        return $than === null || self::compare($candidate, $than) > 0;
    }

    /**
     * @param  iterable<string>  $versions
     */
    public static function highest(iterable $versions): ?string
    {
        $highest = null;

        foreach ($versions as $version) {
            if ($highest === null || self::compare($version, $highest) > 0) {
                $highest = $version;
            }
        }

        return $highest;
    }

    private static function normalize(string $value): string
    {
        return ltrim($value, 'vV');
    }
}
