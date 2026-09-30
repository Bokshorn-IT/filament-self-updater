<?php

declare(strict_types=1);

use BokshornIt\FilamentSelfUpdater\Sources\GitHubSource;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * @return list<array{name: string}>
 */
function tags(string ...$names): array
{
    return array_map(fn (string $name): array => ['name' => $name], $names);
}

it('picks the highest version, not the first tag GitHub lists', function (): void {
    Http::fake([
        'api.github.com/repos/acme/app/tags*' => Http::response(tags('v1.9.0', 'v1.10.0', 'v1.2.0')),
    ]);

    expect((new GitHubSource('acme/app'))->latestVersion())->toBe('v1.10.0');
});

it('reads every page of tags', function (): void {
    $firstPage = tags(...array_map(fn (int $patch): string => "v1.0.{$patch}", range(0, 99)));

    Http::fake([
        'api.github.com/repos/acme/app/tags?per_page=100&page=1' => Http::response($firstPage),
        'api.github.com/repos/acme/app/tags?per_page=100&page=2' => Http::response(tags('v2.0.0')),
    ]);

    expect((new GitHubSource('acme/app'))->latestVersion())->toBe('v2.0.0');

    Http::assertSentCount(2);
});

it('skips prerelease and non-version tags unless prereleases are asked for', function (): void {
    Http::fake([
        'api.github.com/repos/acme/app/tags*' => Http::response(tags('v1.0.0', 'v2.0.0-beta.1', 'nightly')),
    ]);

    expect((new GitHubSource('acme/app'))->latestVersion())->toBe('v1.0.0')
        ->and((new GitHubSource('acme/app', prereleases: true))->latestVersion())->toBe('v2.0.0-beta.1');
});

it('returns null for a repository without versions', function (): void {
    Http::fake(['api.github.com/repos/acme/app/tags*' => Http::response([])]);

    expect((new GitHubSource('acme/app'))->latestVersion())->toBeNull();
});

it('reads published releases, never drafts, prereleases only on request', function (): void {
    Http::fake([
        'api.github.com/repos/acme/app/releases*' => Http::response([
            ['tag_name' => 'v3.0.0', 'draft' => true, 'prerelease' => false],
            ['tag_name' => 'v2.1.0', 'draft' => false, 'prerelease' => true],
            ['tag_name' => 'v2.0.0', 'draft' => false, 'prerelease' => false],
        ]),
    ]);

    expect((new GitHubSource('acme/app', versionsFrom: 'releases'))->latestVersion())->toBe('v2.0.0')
        ->and((new GitHubSource('acme/app', versionsFrom: 'releases', prereleases: true))->latestVersion())->toBe('v2.1.0');
});

it('sends the token when one is configured', function (): void {
    Http::fake(['api.github.com/*' => Http::response(tags('v1.0.0'))]);

    (new GitHubSource('acme/app', token: 'secret-token'))->latestVersion();

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer secret-token'));
});

it('downloads the source archive of a version to the target', function (): void {
    Http::fake([
        'api.github.com/repos/acme/app/zipball/v1.2.0' => Http::response('archive-bytes'),
    ]);

    $target = $this->sandbox('release.zip');

    (new GitHubSource('acme/app'))->download('v1.2.0', $target);

    expect(file_get_contents($target))->toBe('archive-bytes');
});

it('throws when GitHub answers with an error', function (): void {
    Http::fake(['api.github.com/*' => Http::response(['message' => 'Not Found'], 404)]);

    (new GitHubSource('acme/app'))->latestVersion();
})->throws(RequestException::class);

it('says what is missing when no repository is configured', function (): void {
    (new GitHubSource(''))->latestVersion();
})->throws(InvalidArgumentException::class, 'UPDATER_REPOSITORY');
