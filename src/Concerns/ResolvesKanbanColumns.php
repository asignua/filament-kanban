<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Concerns;

use Asignua\FilamentKanban\Support\KanbanColumn;
use Asignua\FilamentKanban\Support\StatusSource;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use LogicException;
use ReflectionClass;
use ReflectionMethod;
use UnitEnum;

/**
 * Where the columns come from: a status enum (`$statusEnum`), or an overridden `statuses()` returning arrays
 * (`id`, `title`, optional `value`, `color`, `icon`, `collapsed`, `meta`) such as rows of a "stages" table.
 */
trait ResolvesKanbanColumns
{
    /** @var class-string<UnitEnum> */
    protected static string $statusEnum;

    protected static string $recordStatusAttribute = 'status';

    /** Ids of the columns that start folded (a status array may also carry `'collapsed' => true`). */
    /** @var list<string> */
    protected array $collapsedStatuses = [];

    /** Whether a column header can fold its column. */
    protected bool $collapsibleColumns = true;

    /** @var Collection<int, KanbanColumn>|null */
    private ?Collection $kanbanDefinitions = null;

    /**
     * Status arrays, mokhosh-style. Override to read them from the database.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function statuses(): Collection
    {
        if (!isset(static::$statusEnum)) {
            throw new LogicException(static::class.' needs a status source: set `protected static string $statusEnum` to an enum, or override statuses().');
        }

        // mokhosh reads `$statusEnum::statuses()`, so an enum that shapes its own columns keeps working.
        if (method_exists(static::$statusEnum, 'statuses')) {
            /** @var Collection<int, array<string, mixed>> $statuses */
            $statuses = (static::$statusEnum)::statuses();

            return $statuses;
        }

        return StatusSource::fromEnum(static::$statusEnum);
    }

    /**
     * The columns without their cards. Override to build [KanbanColumn] yourself; the result is cached for the request.
     *
     * @return Collection<int, KanbanColumn>
     */
    protected function columnDefinitions(): Collection
    {
        return $this->kanbanDefinitions ??= $this->statuses()
            ->map(function (array $status): KanbanColumn {
                $column = KanbanColumn::fromArray($status);

                return in_array($column->id, array_map('strval', $this->collapsedStatuses), true) && !$column->collapsed
                    ? new KanbanColumn($column->id, $column->title, $column->value, $column->color, $column->icon, true, $column->records, $column->meta)
                    : $column;
            })
            ->values();
    }

    /**
     * The columns with the cards that belong to them. Cards whose status matches no column are not shown.
     *
     * @return Collection<int, KanbanColumn>
     */
    protected function columns(): Collection
    {
        if ($this->usesLazyColumns()) {
            return $this->decorateColumns($this->lazyColumns());
        }

        $grouped = $this->records()->groupBy(fn (Model $record): string => $this->statusOf($record));

        return $this->decorateColumns(
            $this->columnDefinitions()->map(
                fn (KanbanColumn $column): KanbanColumn => $column->withRecords(
                    ($grouped->get($column->id) ?? collect())->values(),
                ),
            ),
        );
    }

    /**
     * Cards per page of a column, or null when it loads all of them. Always null on a board that overrides records():
     * that method returns every card at once, so there is nothing to page.
     */
    public function columnPageSize(KanbanColumn $column): ?int
    {
        if ($this->recordsIsOverridden()) {
            return null;
        }

        $size = $this->recordsPerColumn($column);

        return $size === null ? null : max(1, $size);
    }

    /**
     * How many cards of the column are loaded now: its page size, or more once "Show more" or a drop extended it.
     */
    protected function loadedLimitOf(KanbanColumn $column): ?int
    {
        $page = $this->columnPageSize($column);

        return $page === null ? null : max($page, (int) ($this->kanbanColumnLimits[$column->id] ?? 0));
    }

    /**
     * Back to the first page of every lazy column (the set of cards changed: a search, a filter, another tab).
     */
    protected function resetColumnLimits(): void
    {
        $this->kanbanColumnLimits = [];
    }

    /**
     * "Show more" of one lazy column: one more page. A column that does not paginate, or does not exist, is refused.
     */
    public function loadMoreRecords(string $columnId): void
    {
        $column = $this->findColumn($columnId);

        if ($column === null) {
            $this->kanbanNotify(__('filament-kanban::filament-kanban.column_unknown'), 'danger');

            return;
        }

        $page = $this->columnPageSize($column);

        if ($page === null) {
            return;
        }

        $this->kanbanColumnLimits[$column->id] = ($this->loadedLimitOf($column) ?? $page) + $page;
    }

    /**
     * The filtered, ordered query of one column's cards, without a limit. The base for the loaded page, for
     * its total and for add-on aggregates over the whole column (sums).
     *
     * @return Builder<Model>
     */
    protected function columnRecordsQuery(KanbanColumn $column): Builder
    {
        $query = $this->filteredRecordsQuery();

        return $query->where($query->getModel()->qualifyColumn(static::$recordStatusAttribute), $this->statusScalar($column));
    }

    private function usesLazyColumns(): bool
    {
        return $this->columnDefinitions()->contains(fn (KanbanColumn $column): bool => $this->columnPageSize($column) !== null);
    }

    /**
     * A subclass that overrides records() builds the cards by hand, so no page can be cut from them. The trait
     * method's own file tells whether it was replaced.
     */
    private function recordsIsOverridden(): bool
    {
        return (new ReflectionMethod($this, 'records'))->getFileName() !== (new ReflectionClass(QueriesKanbanRecords::class))->getFileName();
    }

    /**
     * Columns of a board with lazy columns: one query per lazy column (a page of cards), one grouped count for all the
     * totals, and one query for every column that is not lazy. The same filtered query feeds all of them, so the badge,
     * the page and the search always agree. Only plain `where status = value` and `limit` are used (no window functions),
     * so it behaves the same on MySQL, MariaDB, PostgreSQL and SQLite.
     *
     * @return Collection<int, KanbanColumn>
     */
    private function lazyColumns(): Collection
    {
        $definitions = $this->columnDefinitions();
        $totals = $this->lazyColumnTotals();

        $plain = $definitions->filter(fn (KanbanColumn $column): bool => $this->columnPageSize($column) === null);
        $plainRecords = collect();

        if ($plain->isNotEmpty()) {
            $query = $this->filteredRecordsQuery();
            $query->whereIn(
                $query->getModel()->qualifyColumn(static::$recordStatusAttribute),
                $plain->map(fn (KanbanColumn $column): int|string => $this->statusScalar($column))->values()->all(),
            );

            $plainRecords = $this->orderedRecordsQuery($query)->get()->groupBy(fn (Model $record): string => $this->statusOf($record));
        }

        return $definitions->map(function (KanbanColumn $column) use ($totals, $plainRecords): KanbanColumn {
            $limit = $this->loadedLimitOf($column);

            if ($limit === null) {
                return $column->withRecords(($plainRecords->get($column->id) ?? collect())->values());
            }

            $records = $this->orderedRecordsQuery($this->columnRecordsQuery($column))->limit($limit)->get();

            return $column->withRecords($records)->withTotal($totals[(string) $this->statusScalar($column)] ?? 0);
        })->values();
    }

    /**
     * @return array<string, int> raw status value => cards matching the search and filters, one grouped query
     */
    private function lazyColumnTotals(): array
    {
        $query = $this->filteredRecordsQuery()->reorder();
        $status = $query->getModel()->qualifyColumn(static::$recordStatusAttribute);

        // Counted over a sub-select, so a modifyRecordsQuery() that adds groups, havings or selects cannot corrupt
        // the count: the inner query only has to yield one row per card.
        $cards = $query->toBase()->select($status.' as kanban_status');
        $rows = $query->getModel()->getConnection()->query()
            ->fromSub($cards, 'kanban_cards')
            ->select('kanban_status')
            ->selectRaw('count(*) as kanban_total')
            ->groupBy('kanban_status')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $value = $row->kanban_status ?? '';
            $total = $row->kanban_total ?? 0;
            $counts[is_bool($value) ? (string) (int) $value : (is_scalar($value) ? (string) $value : '')] = is_numeric($total) ? (int) $total : 0;
        }

        return $counts;
    }

    /**
     * The value a column stores in the status attribute, as something a query binding takes.
     */
    protected function statusScalar(KanbanColumn $column): int|string
    {
        $value = $column->value;

        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            // false/true are stored as 0/1; (string) false would be '' and match nothing.
            is_bool($value) => (int) $value,
            is_int($value) => $value,
            is_scalar($value) => (string) $value,
            default => '',
        };
    }

    /**
     * Last look at the finished columns, e.g. to attach `withMeta(['limit' => 5])` for a header extra.
     *
     * @param Collection<int, KanbanColumn> $columns
     *
     * @return Collection<int, KanbanColumn>
     */
    protected function decorateColumns(Collection $columns): Collection
    {
        return $columns;
    }

    protected function findColumn(string $id): ?KanbanColumn
    {
        return $this->columnDefinitions()->first(fn (KanbanColumn $column): bool => $column->id === $id);
    }

    /**
     * The status of a record as the column id it belongs to ('' = none).
     */
    protected function statusOf(Model $record): string
    {
        $value = $record->getAttribute(static::$recordStatusAttribute);

        return match (true) {
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof UnitEnum => $value->name,
            is_scalar($value) => (string) $value,
            default => '',
        };
    }

    public function isColumnsCollapsible(): bool
    {
        return $this->collapsibleColumns;
    }
}
