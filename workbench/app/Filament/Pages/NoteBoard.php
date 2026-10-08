<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Pages;

use Asignua\FilamentKanban\Pages\KanbanBoard;
use Illuminate\Support\Collection;
use Workbench\App\Models\Note;

/**
 * No sort attribute: the model brings `ordered()` / `setNewOrder()` (spatie/eloquent-sortable style).
 */
class NoteBoard extends KanbanBoard
{
    protected static string $model = Note::class;

    protected static ?string $slug = 'notes-board';

    protected function statuses(): Collection
    {
        return collect([['id' => 'open', 'title' => 'Open'], ['id' => 'shut', 'title' => 'Shut']]);
    }
}
