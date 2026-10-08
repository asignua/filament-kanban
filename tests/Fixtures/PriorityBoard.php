<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Tests\Fixtures;

use Asignua\FilamentKanban\Pages\KanbanBoard;

class PriorityBoard extends KanbanBoard
{
    protected static string $model = PriorityCard::class;

    protected static string $statusEnum = Priority::class;

    protected static string $recordStatusAttribute = 'priority';

    protected static ?string $recordSortAttribute = 'position';
}
