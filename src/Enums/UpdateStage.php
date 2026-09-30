<?php

declare(strict_types=1);

namespace BokshornIt\FilamentSelfUpdater\Enums;

enum UpdateStage: string
{
    case Download = 'download';
    case Extract = 'extract';
    case Install = 'install';
    case PostUpdate = 'post_update';

    public function getLabel(): string
    {
        return __("filament-self-updater::self-updater.stage.{$this->value}");
    }

    /**
     * The line the update log opens the stage with. English whatever the
     * app's locale, like the rest of the log.
     */
    public function getLogLine(): string
    {
        return match ($this) {
            self::Download => 'Downloading ...',
            self::Extract => 'Extracting ...',
            self::Install => 'Installing files ...',
            self::PostUpdate => 'Running post-update commands ...',
        };
    }

    /**
     * Whether a failure in this stage leaves the application files untouched.
     */
    public function leavesFilesUntouched(): bool
    {
        return $this === self::Download || $this === self::Extract;
    }
}
