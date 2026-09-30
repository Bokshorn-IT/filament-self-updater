<?php

declare(strict_types=1);

namespace BokshornIt\FilamentSelfUpdater\Sources;

use BokshornIt\FilamentSelfUpdater\Contracts\ReleaseSource;
use BokshornIt\FilamentSelfUpdater\Support\Version;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Reads versions from a GitHub repository's tags or releases and downloads
 * the source archive of a version.
 */
class GitHubSource implements ReleaseSource
{
    public const FROM_TAGS = 'tags';

    public const FROM_RELEASES = 'releases';

    /**
     * GitHub caps a page at 100 entries. Ten pages cover any repository that
     * tags its releases, and stop a runaway loop on one that tags every build.
     */
    protected const MAX_PAGES = 10;

    public function __construct(
        protected string $repository,
        protected ?string $token = null,
        protected string $versionsFrom = self::FROM_TAGS,
        protected bool $prereleases = false,
        protected int $timeout = 300,
        protected string $baseUrl = 'https://api.github.com',
    ) {}

    public function latestVersion(): ?string
    {
        $this->ensureConfigured();

        $names = $this->versionsFrom === self::FROM_RELEASES
            ? $this->releaseNames()
            : $this->tagNames();

        return Version::highest(array_filter(
            $names,
            fn (string $name): bool => $this->prereleases ? Version::isVersion($name) : Version::isStable($name),
        ));
    }

    public function download(string $version, string $target): void
    {
        $this->ensureConfigured();

        $this->client()
            ->timeout($this->timeout)
            ->sink($target)
            ->get("/repos/{$this->repository}/zipball/".rawurlencode($version))
            ->throw();
    }

    /**
     * @return list<string>
     */
    protected function tagNames(): array
    {
        return array_map(
            fn (array $tag): string => (string) $tag['name'],
            $this->paginate("/repos/{$this->repository}/tags"),
        );
    }

    /**
     * Drafts are never offered. Prereleases only when they are asked for,
     * since GitHub flags them independently of the tag name.
     *
     * @return list<string>
     */
    protected function releaseNames(): array
    {
        $releases = array_filter(
            $this->paginate("/repos/{$this->repository}/releases"),
            fn (array $release): bool => ! ($release['draft'] ?? false)
                && ($this->prereleases || ! ($release['prerelease'] ?? false)),
        );

        return array_values(array_map(
            fn (array $release): string => (string) $release['tag_name'],
            $releases,
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function paginate(string $path): array
    {
        $items = [];

        for ($page = 1; $page <= static::MAX_PAGES; $page++) {
            $batch = $this->client()
                ->get($path, ['per_page' => 100, 'page' => $page])
                ->throw()
                ->json();

            if (! is_array($batch) || $batch === []) {
                break;
            }

            array_push($items, ...array_values(array_filter($batch, 'is_array')));

            if (count($batch) < 100) {
                break;
            }
        }

        return $items;
    }

    /**
     * Checked on use rather than on construction, so an unconfigured
     * application can still render the update page and say what is missing.
     */
    protected function ensureConfigured(): void
    {
        if ($this->repository === '') {
            throw new InvalidArgumentException('No repository is configured. Set UPDATER_REPOSITORY to "owner/name".');
        }

        if (preg_match('#^[\w.-]+/[\w.-]+$#', $this->repository) !== 1) {
            throw new InvalidArgumentException("The repository must be given as \"owner/name\", got [{$this->repository}].");
        }

        if (! in_array($this->versionsFrom, [self::FROM_TAGS, self::FROM_RELEASES], true)) {
            throw new InvalidArgumentException("Versions are read from \"tags\" or \"releases\", got [{$this->versionsFrom}].");
        }
    }

    protected function client(): PendingRequest
    {
        $request = Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->withUserAgent(config('app.name', 'Laravel').' self-updater')
            ->timeout(30);

        if (filled($this->token)) {
            $request = $request->withToken($this->token);
        }

        return $request;
    }
}
