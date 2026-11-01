<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Pages;

use Asignua\FilamentKanban\Pages\KanbanBoard;
use Workbench\App\Enums\TaskStatus;
use Workbench\App\Models\Task;

/**
 * Lazy columns: 25 cards per column and a "Show more" button (seed hundreds of cards to see it).
 */
class BigBoard extends KanbanBoard
{
    protected static string $model = Task::class;

    protected static string $statusEnum = TaskStatus::class;

    protected static ?string $recordSortAttribute = 'position';

    protected static ?string $slug = 'big-board';

    protected static ?string $navigationLabel = 'Big board (lazy)';

    protected ?int $recordsPerColumn = 25;
}
