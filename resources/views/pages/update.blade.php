@php
    use BokshornIt\FilamentSelfUpdater\Enums\UpdateState;
    use Illuminate\Support\Str;

    $status = $this->getStatus();
    $running = $this->isRunning();
    $tone = match (true) {
        $checkError !== null => 'danger',
        $updateAvailable => 'warning',
        default => 'success',
    };
@endphp

<x-filament-panels::page>
    {{-- An element of its own rather than an attribute on the wrapper:
         Livewire starts a poll for an element that appears, but not for an
         attribute added to one that is already on the page. Outside the flex
         column, so it takes no gap there. --}}
    @if ($running || $watching)
        <div wire:poll.2s="pollStatus" style="display: none"></div>
    @endif

    <div class="flex flex-col gap-6">
        <section
            @class([
                'rounded-xl p-6 ring-1',
                'bg-success-50 ring-success-600/20 dark:bg-success-500/10 dark:ring-success-400/30' => $tone === 'success',
                'bg-warning-50 ring-warning-600/20 dark:bg-warning-500/10 dark:ring-warning-400/30' => $tone === 'warning',
                'bg-danger-50 ring-danger-600/20 dark:bg-danger-500/10 dark:ring-danger-400/30' => $tone === 'danger',
            ])
        >
            <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div
                        @class([
                            'flex size-12 shrink-0 items-center justify-center rounded-full',
                            'bg-success-500/10 text-success-600 dark:text-success-400' => $tone === 'success',
                            'bg-warning-500/10 text-warning-600 dark:text-warning-400' => $tone === 'warning',
                            'bg-danger-500/10 text-danger-600 dark:text-danger-400' => $tone === 'danger',
                        ])
                    >
                        <x-filament::icon
                            :icon="match ($tone) {
                                'danger' => 'heroicon-o-exclamation-triangle',
                                'warning' => 'heroicon-o-arrow-up-circle',
                                default => 'heroicon-o-check-badge',
                            }"
                            class="size-7"
                        />
                    </div>

                    <div>
                        <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                            {{ match ($tone) {
                                'danger' => __('filament-self-updater::self-updater.status.check_failed'),
                                'warning' => __('filament-self-updater::self-updater.status.available'),
                                default => __('filament-self-updater::self-updater.status.up_to_date'),
                            } }}
                        </h2>
                        <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">
                            {{ match ($tone) {
                                'danger' => $checkError,
                                'warning' => __('filament-self-updater::self-updater.status.ready'),
                                default => __('filament-self-updater::self-updater.status.latest_running'),
                            } }}
                        </p>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    @if ($this->installAction->isVisible())
                        {{ $this->installAction }}
                    @endif

                    {{ $this->checkAction }}
                </div>
            </div>

            <div
                @class([
                    'mt-6 flex flex-wrap items-center gap-x-8 gap-y-4 border-t pt-6',
                    'border-success-600/15 dark:border-success-400/20' => $tone === 'success',
                    'border-warning-600/15 dark:border-warning-400/20' => $tone === 'warning',
                    'border-danger-600/15 dark:border-danger-400/20' => $tone === 'danger',
                ])
            >
                <div class="flex flex-col gap-1">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('filament-self-updater::self-updater.status.installed_version') }}</span>
                    <span class="font-mono text-lg font-semibold text-gray-950 dark:text-white">{{ $installedVersion ?? __('filament-self-updater::self-updater.status.unknown') }}</span>
                </div>

                @if ($updateAvailable)
                    <x-filament::icon icon="heroicon-m-arrow-long-right" class="size-5 shrink-0 text-gray-400 dark:text-gray-500" />
                @endif

                @if ($availableVersion !== null)
                    <div class="flex flex-col gap-1">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('filament-self-updater::self-updater.status.latest_version') }}</span>
                        <span class="flex items-center gap-2">
                            <span class="font-mono text-lg font-semibold text-gray-950 dark:text-white">{{ $availableVersion }}</span>
                            <x-filament::badge :color="$updateAvailable ? 'warning' : 'success'">
                                {{ $updateAvailable ? __('filament-self-updater::self-updater.status.badge_new') : __('filament-self-updater::self-updater.status.badge_current') }}
                            </x-filament::badge>
                        </span>
                    </div>
                @endif
            </div>
        </section>

        @if ($status !== null)
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('filament-self-updater::self-updater.run.heading') }}
                </x-slot>

                <x-slot name="afterHeader">
                    <x-filament::badge
                        :color="match ($status->state) {
                            UpdateState::Succeeded => 'success',
                            UpdateState::Failed => 'danger',
                            UpdateState::Running => 'info',
                            default => 'gray',
                        }"
                    >
                        {{ $running || $status->state->isFinished() ? $status->state->getLabel() : __('filament-self-updater::self-updater.state.abandoned') }}
                    </x-filament::badge>
                </x-slot>

                <dl class="flex flex-wrap gap-x-8 gap-y-4 text-sm">
                    <div class="flex flex-col gap-1">
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('filament-self-updater::self-updater.run.version') }}</dt>
                        <dd class="font-mono text-gray-950 dark:text-white">{{ $status->from ?? __('filament-self-updater::self-updater.status.unknown') }} → {{ $status->version }}</dd>
                    </div>

                    <div class="flex flex-col gap-1">
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('filament-self-updater::self-updater.run.queued_at') }}</dt>
                        <dd class="text-gray-950 dark:text-white">{{ $status->queuedAt->timezone(config('app.timezone'))->isoFormat('L LTS') }}</dd>
                    </div>

                    @if ($status->finishedAt !== null)
                        <div class="flex flex-col gap-1">
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('filament-self-updater::self-updater.run.finished_at') }}</dt>
                            <dd class="text-gray-950 dark:text-white">{{ $status->finishedAt->timezone(config('app.timezone'))->isoFormat('L LTS') }}</dd>
                        </div>
                    @endif

                    @if ($status->stage !== null && ! $status->state->isFinished())
                        <div class="flex flex-col gap-1">
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('filament-self-updater::self-updater.run.stage') }}</dt>
                            <dd class="flex items-center gap-2 text-gray-950 dark:text-white">
                                @if ($running)
                                    <x-filament::loading-indicator class="size-4" />
                                @endif
                                {{ $status->stage->getLabel() }}
                            </dd>
                        </div>
                    @endif
                </dl>

                @if ($status->state === UpdateState::Failed)
                    <p class="mt-4 text-sm text-danger-600 dark:text-danger-400">
                        {{ $status->filesUpdated()
                            ? __('filament-self-updater::self-updater.run.failed_after', ['version' => $status->version])
                            : __('filament-self-updater::self-updater.run.failed_before') }}
                        @if ($status->message)
                            <span class="font-mono">{{ $status->message }}</span>
                        @endif
                    </p>
                @elseif ($status->state === UpdateState::Queued && $running)
                    <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                        {{ __('filament-self-updater::self-updater.run.waiting_for_worker') }}
                    </p>
                @endif

                @if (filled($log = $this->getLogTail()))
                    {{-- Alpine state, not <details>: the poll re-renders the page
                         every two seconds and would close a <details> each time. --}}
                    <div x-data="{ open: @js($status->state === UpdateState::Failed) }" class="mt-4">
                        <x-filament::link tag="button" type="button" x-on:click="open = ! open" color="gray" size="sm">
                            <span x-show="! open">{{ trans_choice('filament-self-updater::self-updater.run.show_log', $lines = substr_count($log, "\n") + 1 + ($omitted = $this->getOmittedLogLines()), ['lines' => $lines]) }}</span>
                            <span x-show="open" x-cloak>{{ __('filament-self-updater::self-updater.run.hide_log') }}</span>
                        </x-filament::link>

                        @if ($omitted > 0)
                            <p x-show="open" x-cloak class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                {{ trans_choice('filament-self-updater::self-updater.run.log_truncated', $omitted, ['lines' => $omitted, 'file' => str_replace('\\', '/', Str::after((string) $this->getLogPath(), base_path().DIRECTORY_SEPARATOR))]) }}
                            </p>
                        @endif

                        <pre x-show="open" x-cloak class="mt-3 max-h-96 overflow-auto rounded-lg bg-gray-950 p-4 font-mono text-xs leading-relaxed text-gray-100 dark:bg-black/40">{{ $log }}</pre>
                    </div>
                @endif
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
