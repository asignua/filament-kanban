<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban;

use Asignua\FilamentKanban\Commands\InstallCommand;
use Asignua\FilamentKanban\Commands\MakeKanbanBoardCommand;
use Asignua\FilamentKanban\Events\KanbanBoardChanged;
use Asignua\FilamentKanban\Support\Realtime;
use Filament\Facades\Filament;
use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Throwable;

class KanbanServiceProvider extends PackageServiceProvider
{
    public const string PACKAGE = 'asignua/filament-kanban';

    public const string STYLESHEET = 'filament-kanban';

    public const string COMPONENT = 'kanban-board';

    public static string $name = 'filament-kanban';

    public function configurePackage(Package $package): void
    {
        // Translations live in resources/lang/<locale>/filament-kanban.php and are read as
        // `__('filament-kanban::filament-kanban.<key>')`. Publish tag: `filament-kanban-translations`.
        $package->name(static::$name)
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews()
            ->hasCommands([MakeKanbanBoardCommand::class, InstallCommand::class]);
    }

    public function packageBooted(): void
    {
        // Both are loaded by the board view itself, so a panel page without a board pays nothing for them.
        FilamentAsset::register([
            Css::make(self::STYLESHEET, __DIR__.'/../resources/dist/filament-kanban.css')->loadedOnRequest(),
            AlpineComponent::make(self::COMPONENT, __DIR__.'/../resources/dist/kanban-board.js'),
        ], self::PACKAGE);

        // Real-time is opt-in; resolved at request time, so a panel that never enables it pays one config read.
        FilamentView::registerRenderHook(
            PanelsRenderHook::SCRIPTS_AFTER,
            fn (): string => Realtime::enabled() ? view('filament-kanban::hooks.echo')->render() : '',
        );

        // Not at boot: the broadcaster may be set by the host's own provider, and Filament builds panels lazily.
        $this->app->booted($this->registerBroadcasting(...));
    }

    private function registerBroadcasting(): void
    {
        if (!Realtime::enabled()) {
            return;
        }

        // The panel's guard, not the default one: a panel on `->authGuard('admin')` has nobody on `web`.
        try {
            $guard = Filament::getCurrentOrDefaultPanel()?->getAuthGuard();
        } catch (Throwable) {
            $guard = null;
        }

        // Same rule as opening the board (its canAccess()), plus the optional Realtime::authorizeUsing().
        Broadcast::channel(
            KanbanBoardChanged::CHANNEL.'{board}',
            fn (Authenticatable $user, string $board): bool => Realtime::authorizeChannel($user, $board, $guard),
            $guard !== null ? ['guards' => [$guard]] : [],
        );

        // Private channels need /broadcasting/auth; many panels never set broadcasting up.
        if (Realtime::registerAuthRoute() && !Route::has('broadcasting.auth')) {
            Broadcast::routes(['middleware' => ['web', $guard !== null ? 'auth:'.$guard : 'auth']]);
        }
    }
}
