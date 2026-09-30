<?php

declare(strict_types=1);

namespace BokshornIt\FilamentSelfUpdater\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use BokshornIt\FilamentSelfUpdater\FilamentSelfUpdaterServiceProvider;
use BokshornIt\FilamentSelfUpdater\Tests\Fixtures\TestPanelProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /**
     * A scratch directory per test, holding the fake application root and
     * the updater's state directory.
     */
    protected string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('testing'));

        Model::shouldBeStrict();
    }

    protected function tearDown(): void
    {
        if (isset($this->sandbox)) {
            (new Filesystem)->deleteDirectory($this->sandbox);
        }

        parent::tearDown();
    }

    public function sandbox(string $path = ''): string
    {
        if (! isset($this->sandbox)) {
            $this->sandbox = sys_get_temp_dir().DIRECTORY_SEPARATOR.'self-updater-'.bin2hex(random_bytes(6));
            mkdir($this->sandbox, 0755, true);
        }

        return $path === '' ? $this->sandbox : $this->sandbox.DIRECTORY_SEPARATOR.$path;
    }

    /**
     * Filament\Support has to come before Livewire: it rebinds Livewire's
     * DataStore with bind(), which drops the shared instance registered for
     * that key when it runs second.
     */
    protected function getPackageProviders($app): array
    {
        return [
            SupportServiceProvider::class,
            LivewireServiceProvider::class,
            ActionsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentSelfUpdaterServiceProvider::class,
            TestPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('cache.default', 'array');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
