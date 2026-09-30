<?php

declare(strict_types=1);

namespace BokshornIt\FilamentSelfUpdater\Events;

use BokshornIt\FilamentSelfUpdater\Support\UpdateStatus;
use Illuminate\Foundation\Events\Dispatchable;

class UpdateInstalled
{
    use Dispatchable;

    public function __construct(
        public UpdateStatus $status,
    ) {}
}
