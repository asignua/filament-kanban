<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class KanbanServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-kanban';

    public function configurePackage(Package $package): void
    {
        // Translations live in resources/lang/<locale>/filament-kanban.php and are read as
        // `__('filament-kanban::filament-kanban.<key>')`. Publish tag: `filament-kanban-translations`.
        $package->name(static::$name)
            ->hasTranslations()
            ->hasViews();

        // Add a config file only when the plugin really has options: create config/filament-kanban.php and
        // chain `->hasConfigFile()` here (publish tag `filament-kanban-config`). Prefer fluent setters on the Plugin.
    }
}
