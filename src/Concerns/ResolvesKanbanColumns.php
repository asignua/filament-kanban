<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Concerns;

use Asignua\FilamentKanban\Support\KanbanColumn;
use Asignua\FilamentKanban\Support\StatusSource;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use LogicException;
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
