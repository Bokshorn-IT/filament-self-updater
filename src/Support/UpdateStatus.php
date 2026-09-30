<?php

declare(strict_types=1);

namespace BokshornIt\FilamentSelfUpdater\Support;

use BokshornIt\FilamentSelfUpdater\Enums\UpdateStage;
use BokshornIt\FilamentSelfUpdater\Enums\UpdateState;
use Carbon\CarbonImmutable;

/**
 * The state of the latest update run, as kept in the state directory.
 */
final readonly class UpdateStatus
{
    public function __construct(
        public UpdateState $state,
        public string $version,
        public ?string $from,
        public CarbonImmutable $queuedAt,
        public ?UpdateStage $stage = null,
        public ?CarbonImmutable $startedAt = null,
        public ?CarbonImmutable $finishedAt = null,
        public ?string $message = null,
        public ?string $log = null,
    ) {}

    public static function queued(string $version, ?string $from, string $log): self
    {
        return new self(
            state: UpdateState::Queued,
            version: $version,
            from: $from,
            queuedAt: CarbonImmutable::now(),
            log: $log,
        );
    }

    public function running(UpdateStage $stage): self
    {
        return $this->with([
            'state' => UpdateState::Running,
            'stage' => $stage,
            'startedAt' => $this->startedAt ?? CarbonImmutable::now(),
        ]);
    }

    public function succeeded(): self
    {
        return $this->with([
            'state' => UpdateState::Succeeded,
            'finishedAt' => CarbonImmutable::now(),
        ]);
    }

    public function failed(string $message): self
    {
        return $this->with([
            'state' => UpdateState::Failed,
            'finishedAt' => CarbonImmutable::now(),
            'message' => $message,
        ]);
    }

    /**
     * Queued or running, and not older than the timeout. A run past the
     * timeout was abandoned by a worker that died, and must not block the
     * next one.
     */
    public function isActive(int $timeout): bool
    {
        if ($this->state->isFinished()) {
            return false;
        }

        return ($this->startedAt ?? $this->queuedAt)->addSeconds($timeout)->isFuture();
    }

    public function filesUpdated(): bool
    {
        return $this->state === UpdateState::Succeeded
            || ($this->stage !== null && ! $this->stage->leavesFilesUntouched());
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'state' => $this->state->value,
            'version' => $this->version,
            'from' => $this->from,
            'stage' => $this->stage?->value,
            'queued_at' => $this->queuedAt->toIso8601String(),
            'started_at' => $this->startedAt?->toIso8601String(),
            'finished_at' => $this->finishedAt?->toIso8601String(),
            'message' => $this->message,
            'log' => $this->log,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $state = UpdateState::tryFrom((string) ($data['state'] ?? ''));

        if ($state === null || ! is_string($data['version'] ?? null) || ! is_string($data['queued_at'] ?? null)) {
            return null;
        }

        $date = fn (mixed $value): ?CarbonImmutable => is_string($value) ? CarbonImmutable::parse($value) : null;

        return new self(
            state: $state,
            version: $data['version'],
            from: is_string($data['from'] ?? null) ? $data['from'] : null,
            queuedAt: CarbonImmutable::parse($data['queued_at']),
            stage: UpdateStage::tryFrom((string) ($data['stage'] ?? '')),
            startedAt: $date($data['started_at'] ?? null),
            finishedAt: $date($data['finished_at'] ?? null),
            message: is_string($data['message'] ?? null) ? $data['message'] : null,
            log: is_string($data['log'] ?? null) ? $data['log'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function with(array $changes): self
    {
        return new self(...array_merge([
            'state' => $this->state,
            'version' => $this->version,
            'from' => $this->from,
            'queuedAt' => $this->queuedAt,
            'stage' => $this->stage,
            'startedAt' => $this->startedAt,
            'finishedAt' => $this->finishedAt,
            'message' => $this->message,
            'log' => $this->log,
        ], $changes));
    }
}
