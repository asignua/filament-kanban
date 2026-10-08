<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Tests\Fixtures;

use Asignua\FilamentKanban\Pages\KanbanBoard;

class PhaseBoard extends KanbanBoard
{
    protected static string $model = PriorityCard::class;

    protected static string $statusEnum = Phase::class;

    protected static string $recordStatusAttribute = 'phase';
}
