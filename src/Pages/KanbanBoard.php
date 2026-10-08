<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Pages;

use Asignua\FilamentKanban\Concerns\InteractsWithKanban;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * A standalone Kanban page. Extend it, set `$model` and `$statusEnum` (or override `statuses()`), done.
 *
 * Drop-in for `Mokhosh\FilamentKanban\Pages\KanbanBoard`; see README "Migrating from mokhosh/filament-kanban".
 */
class KanbanBoard extends Page
{
    use InteractsWithKanban;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected string $view = 'filament-kanban::kanban-board';
}
