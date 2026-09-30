<?php

declare(strict_types=1);

use BokshornIt\FilamentSelfUpdater\Support\ArchiveInstaller;

/**
 * @param  array<string, string>  $files
 */
function zipOf(string $path, array $files): string
{
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    foreach ($files as $name => $contents) {
        $zip->addFromString($name, $contents);
    }

    $zip->close();

    return $path;
}

beforeEach(function (): void {
    mkdir($this->sandbox('app'));
    mkdir($this->sandbox('work'));

    $this->installer = new ArchiveInstaller($this->sandbox('app'), ['storage', 'vendor/']);
});

it('unwraps the single top-level directory of a GitHub archive', function (): void {
    $archive = zipOf($this->sandbox('release.zip'), ['acme-app-3f2a91c/app/Models/Article.php' => '<?php']);

    $root = $this->installer->extract($archive, $this->sandbox('work'));

    expect($root)->toEndWith('acme-app-3f2a91c')
        ->and(is_file($root.'/app/Models/Article.php'))->toBeTrue();
});

it('refuses an archive with a path leaving the target', function (): void {
    $archive = zipOf($this->sandbox('release.zip'), ['../escape.php' => '<?php']);

    $this->installer->extract($archive, $this->sandbox('work'));
})->throws(RuntimeException::class, 'unsafe path');

it('copies the release over the application, leaving excluded paths alone', function (): void {
    mkdir($this->sandbox('work/release/storage'), 0755, true);
    mkdir($this->sandbox('work/release/app'), 0755, true);
    file_put_contents($this->sandbox('work/release/app/Article.php'), 'new');
    file_put_contents($this->sandbox('work/release/storage/state.json'), 'from the release');
    file_put_contents($this->sandbox('work/release/composer.json'), '{}');

    mkdir($this->sandbox('app/storage'));
    file_put_contents($this->sandbox('app/storage/state.json'), 'kept');

    $written = $this->installer->copy($this->sandbox('work/release'));

    expect($written)->toBe(['app/Article.php', 'composer.json'])
        ->and(file_get_contents($this->sandbox('app/app/Article.php')))->toBe('new')
        ->and(file_get_contents($this->sandbox('app/storage/state.json')))->toBe('kept');
});

it('removes files the previous release installed and the new one dropped', function (): void {
    mkdir($this->sandbox('app/app/Legacy'), 0755, true);
    file_put_contents($this->sandbox('app/app/Legacy/OldReport.php'), 'old');
    file_put_contents($this->sandbox('app/app/Article.php'), 'kept');
    file_put_contents($this->sandbox('app/app/Local.php'), 'never installed by an update');

    $deleted = $this->installer->prune(
        previous: ['app/Article.php', 'app/Legacy/OldReport.php'],
        installed: ['app/Article.php'],
    );

    expect($deleted)->toBe(['app/Legacy/OldReport.php'])
        ->and(is_dir($this->sandbox('app/app/Legacy')))->toBeFalse()
        ->and(is_file($this->sandbox('app/app/Article.php')))->toBeTrue()
        ->and(is_file($this->sandbox('app/app/Local.php')))->toBeTrue();
});

it('never prunes an excluded or escaping path', function (): void {
    mkdir($this->sandbox('app/storage'));
    file_put_contents($this->sandbox('app/storage/data.json'), 'kept');
    file_put_contents($this->sandbox('outside.txt'), 'kept');

    $deleted = $this->installer->prune(['storage/data.json', '../outside.txt'], []);

    expect($deleted)->toBe([])
        ->and(is_file($this->sandbox('app/storage/data.json')))->toBeTrue()
        ->and(is_file($this->sandbox('outside.txt')))->toBeTrue();
});
