<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Tests\Fixtures;

use Asignua\FilamentKanban\Concerns\IsKanbanStatus;
use Illuminate\Support\Collection;

/**
 * A mokhosh-style enum that customises its own static statuses() (mokhosh's board reads `$statusEnum::statuses()`).
 */
enum CustomStatusesEnum: string
{
    use IsKanbanStatus;

    case Todo = 'todo';
    case Doing = 'doing';
    case Done = 'done';

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function statuses(): Collection
    {
        return collect([
            ['id' => 'todo', 'title' => 'Custom backlog'],
            ['id' => 'done', 'title' => 'Custom finished'],
        ]);
    }
}
