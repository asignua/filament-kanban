<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Tests\Fixtures;

use Asignua\FilamentKanban\Pages\KanbanBoard;
use Illuminate\Support\Collection;
use Workbench\App\Enums\TaskStatus;
use Workbench\App\Models\Task;

/**
 * A board written exactly as the mokhosh/filament-kanban README shows it: PUBLIC hooks, a hand-built `records()`
 * and `statuses()`. Only the namespace of the base class changed.
 */
class MokhoshStyleBoard extends KanbanBoard
{
    protected static string $model = Task::class;

    protected static string $statusEnum = TaskStatus::class;

    /** @var list<array<string, mixed>> */
    public static array $calls = [];

    protected function records(): Collection
    {
        return Task::where('locked', false)->get();
    }

    protected function statuses(): Collection
    {
        return collect([
            ['id' => 'todo', 'title' => 'Todo'],
            ['id' => 'doing', 'title' => 'Doing'],
        ]);
    }

    public function onStatusChanged(int|string $recordId, string $status, array $fromOrderedIds, array $toOrderedIds): void
    {
        self::$calls[] = ['status', $recordId, $status, $fromOrderedIds, $toOrderedIds];
        Task::find($recordId)->update(['status' => $status]);
    }

    public function onSortChanged(int|string $recordId, string $status, array $orderedIds): void
    {
        self::$calls[] = ['sort', $recordId, $status, $orderedIds];
    }
}
