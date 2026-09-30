<?php

declare(strict_types=1);

use BokshornIt\FilamentSelfUpdater\Jobs\InstallUpdate;
use BokshornIt\FilamentSelfUpdater\Pages\ApplicationUpdate;
use BokshornIt\FilamentSelfUpdater\Support\StateStore;
use BokshornIt\FilamentSelfUpdater\Support\UpdateStatus;
use BokshornIt\FilamentSelfUpdater\Tests\Fixtures\FakeSource;
use BokshornIt\FilamentSelfUpdater\Tests\Fixtures\TestUser;
use BokshornIt\FilamentSelfUpdater\Updater;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function (): void {
    mkdir($this->sandbox('app'));

    $this->source = new FakeSource([
        'v1.0.0' => ['app/Article.php' => 'v1'],
        'v1.1.0' => ['app/Article.php' => 'v1.1'],
    ]);
    (new StateStore($this->sandbox('state')))->putVersion('v1.0.0');
    $this->updater = makeUpdater($this->source);

    $this->actingAs(TestUser::query()->create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'secret',
        'is_admin' => true,
    ]));
});

it('is closed to users the plugin does not authorize', function (): void {
    $this->actingAs(TestUser::query()->create(['name' => 'Editor', 'email' => 'editor@example.com', 'password' => 'secret', 'is_admin' => false]));

    expect(ApplicationUpdate::canAccess())->toBeFalse();

    Livewire::test(ApplicationUpdate::class)->assertForbidden();
});

it('shows the installed and the newer version', function (): void {
    Livewire::test(ApplicationUpdate::class)
        ->assertSet('installedVersion', 'v1.0.0')
        ->assertSet('availableVersion', 'v1.1.0')
        ->assertSet('updateAvailable', true)
        ->assertActionVisible('install');
});

it('queues the update from the install action', function (): void {
    Queue::fake();

    Livewire::test(ApplicationUpdate::class)
        ->assertDontSeeHtml('wire:poll')
        ->callAction('install')
        ->assertSet('watching', true)
        ->assertActionHidden('install')
        ->assertSee('Queued')
        ->assertSeeHtml('wire:poll.2s="pollStatus"')
        ->assertSee('Show log (1 line)');

    Queue::assertPushed(InstallUpdate::class, fn (InstallUpdate $job): bool => $job->version === 'v1.1.0');
});

it('announces the result once the run finishes', function (): void {
    config(['queue.default' => 'sync']);

    Livewire::test(ApplicationUpdate::class)
        ->callAction('install')
        ->assertSet('watching', false)
        ->assertSet('installedVersion', 'v1.1.0')
        ->assertSet('updateAvailable', false)
        ->assertNotified('Version v1.1.0 installed.');

    expect(file_get_contents($this->sandbox('app/app/Article.php')))->toBe('v1.1');
});

it('asks GitHub again on check', function (): void {
    $page = Livewire::test(ApplicationUpdate::class);
    $this->source->releases['v1.2.0'] = [];

    $page->callAction('check')
        ->assertSet('availableVersion', 'v1.2.0')
        ->assertNotified('Update available: v1.2.0');
});

it('shows the last lines of a long log and says where the rest is', function (): void {
    $state = new StateStore($this->sandbox('state'));
    $status = UpdateStatus::queued('v1.0.0', null, 'long.log')->succeeded();
    $state->putStatus($status);
    $state->appendLog('long.log', implode("\n", array_map(
        fn (int $line): string => sprintf('line %04d', $line),
        range(1, Updater::LOG_LINES + 150),
    )));

    Livewire::test(ApplicationUpdate::class)
        ->assertSee('Show log ('.(Updater::LOG_LINES + 150).' lines)')
        ->assertSee('The first 150 lines are only in the log file')
        ->assertSee('long.log')
        ->assertSee('line 0151')
        ->assertDontSee('line 0150');
});

it('explains a failed lookup instead of breaking the page', function (): void {
    $this->source->unreachable = true;

    Livewire::test(ApplicationUpdate::class)
        ->assertOk()
        ->assertSet('checkError', 'GitHub cannot be reached.')
        ->assertSee('GitHub cannot be reached.')
        ->assertActionHidden('install');
});
