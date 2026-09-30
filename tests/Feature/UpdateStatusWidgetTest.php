<?php

declare(strict_types=1);

use BokshornIt\FilamentSelfUpdater\Support\StateStore;
use BokshornIt\FilamentSelfUpdater\Tests\Fixtures\FakeSource;
use BokshornIt\FilamentSelfUpdater\Tests\Fixtures\TestUser;
use BokshornIt\FilamentSelfUpdater\Widgets\UpdateStatusWidget;
use Livewire\Livewire;

beforeEach(function (): void {
    (new StateStore($this->sandbox('state')))->putVersion('v1.0.0');

    $this->actingAs(TestUser::query()->create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'secret',
        'is_admin' => true,
    ]));
});

it('links to the update page when a newer version exists', function (): void {
    makeUpdater(new FakeSource(['v1.1.0' => []]));

    Livewire::test(UpdateStatusWidget::class)
        ->assertSet('newerVersion', 'v1.1.0')
        ->assertSee('/testing/update');
});

it('shows only the installed version when GitHub cannot be reached', function (): void {
    $source = new FakeSource;
    $source->unreachable = true;
    makeUpdater($source);

    Livewire::test(UpdateStatusWidget::class)
        ->assertOk()
        ->assertSet('newerVersion', null)
        ->assertSee('v1.0.0');
});

it('is hidden from users the plugin does not authorize', function (): void {
    $this->actingAs(TestUser::query()->create(['name' => 'Editor', 'email' => 'editor@example.com', 'password' => 'secret', 'is_admin' => false]));

    expect(UpdateStatusWidget::canView())->toBeFalse();
});
