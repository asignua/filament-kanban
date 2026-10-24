<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Tests\Fixtures;

use Asignua\FilamentKanban\Pages\KanbanBoard;
use Asignua\FilamentKanban\Support\KanbanColumn;
use Workbench\App\Enums\TaskStatus;
use Workbench\App\Models\Task;

/**
 * Every column loads two cards at a time; `$onlyColumn` limits the paging to one column.
 */
class LazyTaskBoard extends KanbanBoard
{
    protected static string $model = Task::class;

    protected static string $statusEnum = TaskStatus::class;

    protected static ?string $recordSortAttribute = 'position';

    protected ?int $recordsPerColumn = 2;

    public static ?string $onlyColumn = null;

    protected function recordsPerColumn(KanbanColumn $column): ?int
    {
        return self::$onlyColumn === null || self::$onlyColumn === $column->id ? parent::recordsPerColumn($column) : null;
    }
}
