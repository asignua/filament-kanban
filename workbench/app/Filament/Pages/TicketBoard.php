<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Pages;

use Asignua\FilamentKanban\Pages\KanbanBoard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Workbench\App\Models\Ticket;

/**
 * ULID keys, a plain string status, statuses from an overridden method, the policy decides who may move.
 */
class TicketBoard extends KanbanBoard
{
    protected static string $model = Ticket::class;

    protected static ?string $recordSortAttribute = 'sort';

    protected static ?string $slug = 'tickets-board';

    protected function statuses(): Collection
    {
        return collect([
            ['id' => 'new', 'title' => 'New'],
            ['id' => 'open', 'title' => 'Open', 'color' => 'info'],
            ['id' => 'closed', 'title' => 'Closed', 'collapsed' => true],
        ]);
    }

    protected function recordUrl(Model $record): ?string
    {
        return null;
    }
}
