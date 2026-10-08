<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Tasks\Pages;

use Asignua\FilamentKanban\Pages\KanbanResourcePage;
use Workbench\App\Enums\TaskStatus;
use Workbench\App\Filament\Resources\Tasks\TaskResource;

/**
 * The same enum board, living on a resource: the model comes from the resource.
 */
class TasksKanban extends KanbanResourcePage
{
    protected static string $resource = TaskResource::class;

    protected static string $statusEnum = TaskStatus::class;

    protected static ?string $recordSortAttribute = 'position';
}
