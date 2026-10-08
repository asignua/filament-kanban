<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Concerns;

use Asignua\FilamentKanban\Support\KanbanColumn;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

/**
 * Seams for subclasses and add-ons to put markup on the board without copying its views.
 *
 * Every slot returns a list of strings (escaped) or Htmlable (printed as is, so escape what you build).
 */
trait HasKanbanSlots
{
    protected static string $toolbarView = 'filament-kanban::kanban-toolbar';

    protected static string $headerView = 'filament-kanban::kanban-header';

    protected static string $recordView = 'filament-kanban::kanban-record';

    protected static string $statusView = 'filament-kanban::kanban-status';

    protected static string $scriptsView = 'filament-kanban::kanban-scripts';

    /**
     * Next to the search box.
     *
     * @return array<int, Htmlable|string>
     */
    protected function toolbarExtras(): array
    {
        return [];
    }

    /**
     * In a column header, after the card counter (a WIP limit, a sum...).
     *
     * @return array<int, Htmlable|string>
     */
    protected function columnHeaderExtras(KanbanColumn $column): array
    {
        return [];
    }

    /**
     * Small labels on a card. Each: `['label' => string, 'color' => ?string, 'icon' => ?string, 'tooltip' => ?string]`.
     *
     * @return array<int, array{label: string, color?: string|null, icon?: string|null, tooltip?: string|null}>
     */
    protected function recordBadges(Model $record, KanbanColumn $column): array
    {
        return [];
    }

    /**
     * Free markup at the bottom of a card.
     *
     * @return array<int, Htmlable|string>
     */
    protected function recordExtras(Model $record, KanbanColumn $column): array
    {
        return [];
    }

    /**
     * Extra CSS classes on a card.
     */
    protected function recordClasses(Model $record, KanbanColumn $column): string
    {
        return '';
    }

    /**
     * The view of the board and the data it receives. Extend, do not replace, so the default views keep working.
     *
     * @return array<string, mixed>
     */
    protected function kanbanViewData(): array
    {
        $columns = $this->columns();

        return [
            'columns' => $columns,
            // mokhosh/filament-kanban's name for the same thing (its views read $statuses).
            'statuses' => $columns,
        ];
    }

    /**
     * @param array<int, Htmlable|string> $slot
     */
    protected function renderKanbanSlot(array $slot): string
    {
        return implode('', array_map(
            static fn (string|Htmlable $part): string => $part instanceof Htmlable ? $part->toHtml() : e($part),
            $slot,
        ));
    }
}
