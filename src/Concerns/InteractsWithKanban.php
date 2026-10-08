<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Concerns;

/**
 * Everything a Kanban page needs, as one trait: use it on any Filament page (or resource page) to turn it into a
 * board. `Pages\KanbanBoard` and `Pages\KanbanResourcePage` are just a page class plus this trait.
 */
trait InteractsWithKanban
{
    use EditsKanbanRecords;
    use HasKanbanSlots;
    use MovesKanbanRecords;
    use QueriesKanbanRecords;
    use ResolvesKanbanColumns;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return $this->kanbanViewData();
    }
}
