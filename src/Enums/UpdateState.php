<?php

declare(strict_types=1);

namespace BokshornIt\FilamentSelfUpdater\Enums;

enum UpdateState: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return __("filament-self-updater::self-updater.state.{$this->value}");
    }

    public function isFinished(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }
}
