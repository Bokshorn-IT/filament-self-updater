<?php

declare(strict_types=1);

namespace BokshornIt\FilamentSelfUpdater\Pages;

use BackedEnum;
use BokshornIt\FilamentSelfUpdater\Enums\UpdateState;
use BokshornIt\FilamentSelfUpdater\Exceptions\UpdateInProgressException;
use BokshornIt\FilamentSelfUpdater\SelfUpdaterPlugin;
use BokshornIt\FilamentSelfUpdater\Support\UpdateStatus;
use BokshornIt\FilamentSelfUpdater\Updater;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Throwable;

class ApplicationUpdate extends Page
{
    protected static ?string $slug = 'update';

    protected string $view = 'filament-self-updater::pages.update';

    public ?string $installedVersion = null;

    public ?string $availableVersion = null;

    public bool $updateAvailable = false;

    public ?string $checkError = null;

    /**
     * Set while the page watches a run, so the poll that sees it finish can
     * announce the result once.
     */
    public bool $watching = false;

    public static function canAccess(): bool
    {
        return SelfUpdaterPlugin::resolve()->isAuthorized();
    }

    public static function getNavigationGroup(): ?string
    {
        return SelfUpdaterPlugin::resolve()->getNavigationGroup();
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return SelfUpdaterPlugin::resolve()->getNavigationIcon();
    }

    public static function getNavigationSort(): ?int
    {
        return SelfUpdaterPlugin::resolve()->getNavigationSort();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return SelfUpdaterPlugin::resolve()->hasNavigation() && static::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-self-updater::self-updater.navigation_label');
    }

    public function getTitle(): string
    {
        return __('filament-self-updater::self-updater.title');
    }

    public function mount(): void
    {
        $this->watching = $this->updater()->isRunning();

        $this->loadVersions();
    }

    public function installAction(): Action
    {
        return Action::make('install')
            ->label(__('filament-self-updater::self-updater.actions.install'))
            ->icon('heroicon-m-arrow-down-tray')
            ->visible(fn (): bool => $this->updateAvailable && ! $this->isRunning())
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-arrow-down-tray')
            ->modalDescription(fn (): string => __('filament-self-updater::self-updater.modal.install_description', [
                'version' => $this->availableVersion,
            ]))
            ->modalSubmitActionLabel(__('filament-self-updater::self-updater.actions.install_now'))
            ->action(function (): void {
                try {
                    $this->updater()->queue((string) $this->availableVersion);
                } catch (UpdateInProgressException) {
                    Notification::make()
                        ->title(__('filament-self-updater::self-updater.notifications.in_progress'))
                        ->warning()
                        ->send();

                    return;
                }

                $this->watching = true;

                // On the sync queue the job has already run by now.
                $this->pollStatus();
            });
    }

    public function checkAction(): Action
    {
        return Action::make('check')
            ->label(__('filament-self-updater::self-updater.actions.check'))
            ->icon('heroicon-m-arrow-path')
            ->color('gray')
            ->outlined()
            ->action(function (): void {
                $this->loadVersions(fresh: true);

                if ($this->checkError !== null) {
                    Notification::make()
                        ->title(__('filament-self-updater::self-updater.notifications.check_failed'))
                        ->body($this->checkError)
                        ->danger()
                        ->send();

                    return;
                }

                $notification = Notification::make()->title($this->updateAvailable
                    ? __('filament-self-updater::self-updater.notifications.available', ['version' => $this->availableVersion])
                    : __('filament-self-updater::self-updater.notifications.up_to_date'));

                ($this->updateAvailable ? $notification->warning() : $notification->success())->send();
            });
    }

    /**
     * Called by the page's poll while a run is active.
     */
    public function pollStatus(): void
    {
        if (! $this->watching || $this->isRunning()) {
            return;
        }

        $this->watching = false;
        $this->loadVersions();

        $status = $this->getStatus();

        if ($status?->state === UpdateState::Succeeded) {
            Notification::make()
                ->title(__('filament-self-updater::self-updater.notifications.installed', ['version' => $status->version]))
                ->success()
                ->send();
        } elseif ($status?->state === UpdateState::Failed) {
            Notification::make()
                ->title($status->filesUpdated()
                    ? __('filament-self-updater::self-updater.notifications.failed_after')
                    : __('filament-self-updater::self-updater.notifications.failed_before'))
                ->danger()
                ->send();
        }
    }

    public function getStatus(): ?UpdateStatus
    {
        return $this->updater()->status();
    }

    public function isRunning(): bool
    {
        return $this->updater()->isRunning();
    }

    public function getLogTail(): string
    {
        $status = $this->getStatus();

        return $status === null ? '' : $this->updater()->logTail($status);
    }

    public function getLogPath(): ?string
    {
        $status = $this->getStatus();

        return $status === null ? null : $this->updater()->logPath($status);
    }

    /**
     * Lines at the start of the log the page leaves out.
     */
    public function getOmittedLogLines(): int
    {
        $status = $this->getStatus();

        return $status === null ? 0 : max(0, $this->updater()->logLineCount($status) - Updater::LOG_LINES);
    }

    protected function loadVersions(bool $fresh = false): void
    {
        $updater = $this->updater();

        $this->installedVersion = $updater->installedVersion();
        $this->checkError = null;

        try {
            $this->availableVersion = $fresh ? $updater->refreshAvailableVersion() : $updater->availableVersion();
            $this->updateAvailable = $updater->isUpdateAvailable();
        } catch (Throwable $exception) {
            $this->availableVersion = null;
            $this->updateAvailable = false;
            $this->checkError = $exception->getMessage();
        }
    }

    protected function updater(): Updater
    {
        return app(Updater::class);
    }
}
