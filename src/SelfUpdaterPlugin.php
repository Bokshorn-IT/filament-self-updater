<?php

declare(strict_types=1);

namespace BokshornIt\FilamentSelfUpdater;

use BokshornIt\FilamentSelfUpdater\Pages\ApplicationUpdate;
use BokshornIt\FilamentSelfUpdater\Widgets\UpdateStatusWidget;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;

class SelfUpdaterPlugin implements Plugin
{
    use EvaluatesClosures;

    public const ID = 'filament-self-updater';

    protected ?string $page = null;

    protected bool|Closure $authorizeUsing = false;

    protected bool|Closure $hasWidget = false;

    protected string|Closure|null $navigationGroup = null;

    protected string|Closure|null $navigationIcon = null;

    protected int|Closure|null $navigationSort = null;

    protected bool|Closure|null $hasNavigation = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(static::ID);

        return $plugin;
    }

    /**
     * The plugin from the current panel, or a plain instance when there is no
     * panel around. The plain instance authorizes nobody.
     */
    public static function resolve(): static
    {
        $panel = Filament::getCurrentPanel();

        if ($panel?->hasPlugin(static::ID)) {
            /** @var static $plugin */
            $plugin = $panel->getPlugin(static::ID);

            return $plugin;
        }

        return app(static::class);
    }

    public function getId(): string
    {
        return static::ID;
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            $this->getPage(),
        ]);

        if ($this->evaluate($this->hasWidget)) {
            $panel->widgets([
                UpdateStatusWidget::class,
            ]);
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * @param  class-string<ApplicationUpdate>  $page
     */
    public function page(string $page): static
    {
        $this->page = $page;

        return $this;
    }

    /**
     * @return class-string<ApplicationUpdate>
     */
    public function getPage(): string
    {
        return $this->page ?? ApplicationUpdate::class;
    }

    /**
     * Who may see the update page and install updates. Nobody until this is
     * set: installing replaces the application's code and runs a shell
     * script, which is not something every panel user should be able to do.
     * The closure receives the authenticated user as $user.
     */
    public function authorize(bool|Closure $condition): static
    {
        $this->authorizeUsing = $condition;

        return $this;
    }

    public function isAuthorized(): bool
    {
        return (bool) $this->evaluate($this->authorizeUsing, [
            'user' => Filament::auth()->user(),
        ]);
    }

    /**
     * Adds a dashboard widget showing the installed version and, when there
     * is one, the newer version with a link to the update page.
     */
    public function widget(bool|Closure $condition = true): static
    {
        $this->hasWidget = $condition;

        return $this;
    }

    public function navigationGroup(string|Closure|null $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->evaluate($this->navigationGroup)
            ?? config('filament-self-updater.navigation.group');
    }

    public function navigationIcon(string|Closure|null $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function getNavigationIcon(): ?string
    {
        return $this->evaluate($this->navigationIcon)
            ?? config('filament-self-updater.navigation.icon', 'heroicon-o-cloud-arrow-down');
    }

    public function navigationSort(int|Closure|null $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function getNavigationSort(): ?int
    {
        return $this->evaluate($this->navigationSort)
            ?? config('filament-self-updater.navigation.sort');
    }

    public function registerNavigation(bool|Closure $condition = true): static
    {
        $this->hasNavigation = $condition;

        return $this;
    }

    public function hasNavigation(): bool
    {
        return $this->evaluate($this->hasNavigation)
            ?? config('filament-self-updater.navigation.enabled', true);
    }
}
