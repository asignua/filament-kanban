<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Concerns;

use Asignua\FilamentKanban\Support\KanbanColumn;
use Asignua\FilamentKanban\Support\PositionWriter;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use LogicException;

/**
 * The cards: which model, how they are fetched, searched and ordered.
 */
trait QueriesKanbanRecords
{
    /** @var class-string<Model> */
    protected static string $model;

    protected static string $recordTitleAttribute = 'title';

    /** Column that stores the manual order; null = the board does not persist an order. */
    protected static ?string $recordSortAttribute = null;

    /** Attributes the header search looks in (dotted = on a relation). Empty = the title attribute. */
    /** @var list<string> */
    protected array $searchableAttributes = [];

    protected bool $searchable = true;

    /** Seconds a card keeps its "just updated" flash. */
    protected int $flashSeconds = 3;

    /**
     * Lazy columns: how many cards a column loads at first and with every "Show more". Null = load them all (the
     * default, exactly the old behaviour). Per-column control: override recordsPerColumn().
     */
    protected ?int $recordsPerColumn = null;

    #[Url(as: 'search', except: '')]
    public string $search = '';

    /**
     * A new search is a new set of cards: lazy columns start again from their first page.
     */
    public function updatedSearch(): void
    {
        $this->resetColumnLimits();
    }

    /**
     * Lazy columns: how many cards of each column are loaded now, by column id. Only columns that were extended
     * ("Show more", a card dropped in) appear here; the rest sit at their page size. Locked: the browser cannot set it.
     *
     * @var array<string, int>
     */
    #[Locked]
    public array $kanbanColumnLimits = [];

    /**
     * @return class-string<Model>
     */
    protected static function kanbanModel(): string
    {
        if (!isset(static::$model)) {
            throw new LogicException(static::class.' needs `protected static string $model`.');
        }

        return static::$model;
    }

    /**
     * The manual-order column. Null = nothing is persisted by position, unless the model brings spatie/eloquent-sortable
     * (then its `ordered()` scope and `setNewOrder()` are used, as in mokhosh/filament-kanban).
     */
    protected static function sortAttribute(): ?string
    {
        return static::$recordSortAttribute;
    }

    /**
     * Every card the board may ever show, with the model's global scopes (tenancy...) and nothing else.
     * Put UI filters in modifyRecordsQuery(): this query also finds the whole column when positions are saved, and
     * a filter here would make hidden cards collide with the visible ones.
     *
     * @return Builder<Model>
     */
    protected function getEloquentQuery(): Builder
    {
        return static::kanbanModel()::query();
    }

    /**
     * Search, filters and any other narrowing of the visible cards. Overridable seam for add-ons.
     *
     * @param Builder<Model> $query
     *
     * @return Builder<Model>
     */
    protected function modifyRecordsQuery(Builder $query): Builder
    {
        return $query;
    }

    /**
     * @return list<string>
     */
    protected function searchableAttributes(): array
    {
        return $this->searchableAttributes === [] ? [static::$recordTitleAttribute] : $this->searchableAttributes;
    }

    public function isSearchable(): bool
    {
        return $this->searchable && $this->searchableAttributes() !== [];
    }

    /**
     * @param Builder<Model> $query
     *
     * @return Builder<Model>
     */
    protected function applySearch(Builder $query): Builder
    {
        $term = trim($this->search);

        if ($term === '' || !$this->isSearchable()) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $query) use ($like): void {
            foreach ($this->searchableAttributes() as $attribute) {
                if (str_contains($attribute, '.')) {
                    [$relation, $column] = explode('.', $attribute, 2);

                    $query->orWhereHas($relation, fn (Builder $related): Builder => $related->where($column, 'like', $like));

                    continue;
                }

                $query->orWhere($query->getModel()->qualifyColumn($attribute), 'like', $like);
            }
        });
    }

    /**
     * Relations to eager load for the cards.
     *
     * @return list<string>
     */
    protected function recordRelations(): array
    {
        return [];
    }

    /**
     * Cards per page of one lazy column; null = the column loads all its cards.
     * Override to paginate only some columns, e.g. the closed ones: `return in_array($column->id, ['won', 'lost'], true) ? 25 : null;`.
     * Pagination works only through the built-in records() query: a board that overrides records() loads everything.
     */
    protected function recordsPerColumn(KanbanColumn $column): ?int
    {
        return $this->recordsPerColumn;
    }

    /**
     * The search, filters and add-on narrowing applied to every card the board may show: no ordering, no eager loads.
     * The one query that cards, lazy-column counts and add-on aggregates are all built from, so they always agree.
     *
     * @return Builder<Model>
     */
    protected function filteredRecordsQuery(): Builder
    {
        return $this->modifyRecordsQuery($this->applySearch($this->getEloquentQuery()));
    }

    /**
     * The visible cards. Overridable (mokhosh-compatible); an override replaces the search and the ordering too,
     * so prefer modifyRecordsQuery() unless you really build the collection by hand.
     *
     * @return Collection<int, Model>
     */
    protected function records(): Collection
    {
        return $this->orderedRecordsQuery($this->filteredRecordsQuery())->get();
    }

    /**
     * Eager loads and the card order (manual position, else the sortable scope, then the key) for a records query.
     *
     * @param Builder<Model> $query
     *
     * @return Builder<Model>
     */
    protected function orderedRecordsQuery(Builder $query): Builder
    {
        $query->with($this->recordRelations());

        $sort = static::sortAttribute();

        if ($sort !== null) {
            PositionWriter::orderByPosition($query, $query->getModel()->qualifyColumn($sort));
        } elseif (method_exists(static::kanbanModel(), 'scopeOrdered')) {
            $query->scopes(['ordered']);
        }

        $key = $query->getModel()->getQualifiedKeyName();

        return $query->orderBy($key);
    }

    protected function findRecord(int|string $recordId): ?Model
    {
        return $this->getEloquentQuery()->whereKey($recordId)->first();
    }

    protected function recordTitle(Model $record): string
    {
        $title = $record->getAttribute(static::$recordTitleAttribute);

        return is_scalar($title) ? (string) $title : '';
    }

    protected function recordDescription(Model $record): ?string
    {
        return null;
    }

    /**
     * Where a card leads instead of opening the edit modal. Null = open the modal (when enabled).
     */
    protected function recordUrl(Model $record): ?string
    {
        return null;
    }

    public function isRecentlyUpdated(Model $record): bool
    {
        if (!$record->usesTimestamps() || $this->flashSeconds <= 0) {
            return false;
        }

        $column = $record->getUpdatedAtColumn();
        $updatedAt = $column === null ? null : $record->getAttribute($column);

        return $updatedAt instanceof DateTimeInterface
            && now()->getTimestamp() - $updatedAt->getTimestamp() < $this->flashSeconds;
    }
}
