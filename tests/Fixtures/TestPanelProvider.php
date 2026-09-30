<?php

declare(strict_types=1);

namespace BokshornIt\FilamentSelfUpdater\Tests\Fixtures;

use BokshornIt\FilamentSelfUpdater\SelfUpdaterPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('testing')
            ->path('testing')
            ->plugin(
                SelfUpdaterPlugin::make()
                    ->authorize(fn (?TestUser $user): bool => $user?->is_admin === true)
                    ->widget()
            );
    }
}
