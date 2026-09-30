<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use BokshornIt\FilamentSelfUpdater\Contracts\ReleaseSource;
use BokshornIt\FilamentSelfUpdater\SelfUpdaterPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Workbench\App\Sources\DemoSource;

/**
 * The demo panel used for the screenshots and for poking at the plugin by
 * hand. Deliberately no ->colors() call: everything renders in Filament's
 * default theme so the package is not shown through someone's brand.
 */
class DemoPanelProvider extends PanelProvider
{
    public function register(): void
    {
        parent::register();

        // Versions come from memory rather than GitHub, and the demo keeps its
        // state next to the rest of the workbench's storage.
        $this->app->bind(ReleaseSource::class, DemoSource::class);

        config([
            'filament-self-updater.storage_path' => storage_path('app/self-updater'),
        ]);
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('demo')
            ->path('demo')
            ->login()
            // Built by `npm run demo:css` with the Tailwind CLI straight into
            // the testbench skeleton's public directory.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<link rel="stylesheet" href="/demo.css" />',
            )
            ->pages([
                Dashboard::class,
            ])
            ->plugin(
                SelfUpdaterPlugin::make()
                    ->authorize(true)
                    ->widget()
                    ->navigationGroup('System')
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
