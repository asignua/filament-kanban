<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Pages;

use Asignua\FilamentKanban\Concerns\InteractsWithKanban;
use Asignua\FilamentKanban\Support\KanbanGate;
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

    /**
     * Like a resource: the model's `viewAny` policy decides who opens the board (no policy = everyone, unless the
     * panel uses strictAuthorization()). Override for your own rule.
     */
    public static function canAccess(): bool
    {
        return KanbanGate::allowsViewAny(static::kanbanModel());
    }
}
