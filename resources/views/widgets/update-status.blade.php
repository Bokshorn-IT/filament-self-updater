<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center gap-x-3">
            <div
                @class([
                    'flex size-10 shrink-0 items-center justify-center rounded-full',
                    'bg-success-500/10 text-success-600 dark:text-success-400' => $newerVersion === null,
                    'bg-warning-500/10 text-warning-600 dark:text-warning-400' => $newerVersion !== null,
                ])
            >
                <x-filament::icon
                    :icon="$newerVersion === null ? 'heroicon-o-check-badge' : 'heroicon-o-arrow-up-circle'"
                    class="size-6"
                />
            </div>

            <div class="flex-1">
                <h2 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    {{ $newerVersion === null ? __('filament-self-updater::self-updater.widget.current') : __('filament-self-updater::self-updater.status.available') }}
                </h2>

                <p class="flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
                    <span class="font-mono">{{ $installedVersion ?? __('filament-self-updater::self-updater.status.unknown') }}</span>

                    @if ($newerVersion !== null)
                        <x-filament::icon icon="heroicon-m-arrow-long-right" class="size-4 shrink-0" />
                        <span class="font-mono font-medium text-warning-600 dark:text-warning-400">{{ $newerVersion }}</span>
                    @endif
                </p>
            </div>

            @if ($newerVersion !== null)
                <x-filament::button
                    tag="a"
                    :href="$pageUrl"
                    color="warning"
                    icon="heroicon-m-arrow-down-tray"
                    labeled-from="sm"
                >
                    {{ __('filament-self-updater::self-updater.actions.open') }}
                </x-filament::button>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
