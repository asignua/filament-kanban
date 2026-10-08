<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Pages;

use Asignua\FilamentKanban\Pages\KanbanBoard;
use Asignua\FilamentKanban\Support\KanbanColumn;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\TaskStatus;
use Workbench\App\Models\Task;

/**
 * The reference board: enum columns, a position column, a transition modal into "done", per-card lock,
 * a folded "done" column, badges, and a search.
 */
class TaskBoard extends KanbanBoard
{
    protected static string $model = Task::class;

    protected static string $statusEnum = TaskStatus::class;

    protected static ?string $recordSortAttribute = 'position';

    protected static ?string $slug = 'tasks-board';

    protected array $collapsedStatuses = ['done'];

    protected function columnTransitionSchema(string $from, string $to): ?array
    {
        return $to === 'done' ? [TextInput::make('reason')->required()] : null;
    }

    protected function isRecordDraggable(Model $record): bool
    {
        return !($record instanceof Task && $record->locked);
    }

    protected function recordBadges(Model $record, KanbanColumn $column): array
    {
        return $record instanceof Task && $record->locked ? [['label' => 'Locked', 'color' => 'danger']] : [];
    }

    protected function columnHeaderExtras(KanbanColumn $column): array
    {
        return ['limit '.$column->id];
    }
}
