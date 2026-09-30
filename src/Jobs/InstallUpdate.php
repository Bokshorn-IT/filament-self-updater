<?php

declare(strict_types=1);

namespace BokshornIt\FilamentSelfUpdater\Jobs;

use BokshornIt\FilamentSelfUpdater\Updater;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class InstallUpdate implements ShouldQueue
{
    use Queueable;

    /**
     * An update is never retried on its own: a second attempt would start
     * over on files the first one may already have replaced.
     */
    public int $tries = 1;

    public int $timeout;

    public bool $failOnTimeout = true;

    public function __construct(
        public string $version,
    ) {
        $this->timeout = (int) config('filament-self-updater.timeout', 1800);
    }

    public function handle(Updater $updater): void
    {
        $updater->run($this->version);
    }

    public function failed(?Throwable $exception): void
    {
        app(Updater::class)->markAbandoned(
            $this->version,
            $exception?->getMessage() ?: 'The update job stopped before it finished.',
        );
    }
}
