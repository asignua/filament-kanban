<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban;

use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * The boards are ordinary Filament pages, so registering the plugin is optional: it exists so the package shows up
 * in a panel's plugin list and gives add-ons (asignua/filament-kanban-pro) a stable id to check with `hasPlugin()`.
 * Register your boards the usual way (`->pages([...])`, `->discoverPages()`, or a resource's `getPages()`).
 */
class KanbanPlugin implements Plugin
{
    public const string ID = 'asignua-filament-kanban';

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void {}
}
