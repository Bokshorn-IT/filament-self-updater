<?php

declare(strict_types=1);

namespace BokshornIt\FilamentSelfUpdater\Events;

use BokshornIt\FilamentSelfUpdater\Support\UpdateStatus;
use Illuminate\Foundation\Events\Dispatchable;
use Throwable;

/**
 * Check $status->filesUpdated(): when true, the files are already on the new
 * version and only a post-update command failed.
 */
class UpdateFailed
{
    use Dispatchable;

    public function __construct(
        public UpdateStatus $status,
        public Throwable $exception,
    ) {}
}
