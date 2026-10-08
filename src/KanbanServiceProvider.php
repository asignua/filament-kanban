<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban;

use Asignua\FilamentKanban\Commands\InstallCommand;
use Asignua\FilamentKanban\Commands\MakeKanbanBoardCommand;
use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

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
    }
}
