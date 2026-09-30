<?php

declare(strict_types=1);

namespace BokshornIt\FilamentSelfUpdater\Widgets;

use BokshornIt\FilamentSelfUpdater\SelfUpdaterPlugin;
use BokshornIt\FilamentSelfUpdater\Updater;
use Filament\Widgets\Widget;
use Throwable;

class UpdateStatusWidget extends Widget
{
    protected static ?int $sort = -2;

    protected string $view = 'filament-self-updater::widgets.update-status';

    public ?string $installedVersion = null;

    /**
     * The newer version, or null when there is none or GitHub cannot be
     * reached. A dashboard is no place for a lookup error.
     */
    public ?string $newerVersion = null;

    public string $pageUrl = '';

    public static function canView(): bool
    {
        return SelfUpdaterPlugin::resolve()->isAuthorized();
    }

    public function mount(): void
    {
        $updater = app(Updater::class);

        $this->installedVersion = $updater->installedVersion();
        $this->pageUrl = SelfUpdaterPlugin::resolve()->getPage()::getUrl();

        try {
            $this->newerVersion = $updater->isUpdateAvailable() ? $updater->availableVersion() : null;
        } catch (Throwable) {
            $this->newerVersion = null;
        }
    }
}
