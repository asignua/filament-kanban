<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;

/**
 * Renumbers one column from the order the browser saw.
 *
 * The board may show only a part of a column (a search, a "mine only" filter), so the ids it reports are the
 * VISIBLE cards. They trade places with each other: the n-th slot that a visible card held is handed to the n-th
 * visible card of the new order, and the hidden cards keep exactly the slots they had. Renumbering the whole column
 * from the visible list instead would push every hidden card to the end on each drag.
 *
 * Ids that are not in the column are ignored. Writes go through the base query builder: no model events, no
 * `updated_at` bump (a bump would make every renumbered card flash as "just updated").
 */
final class PositionWriter
{
    /** Rows per bulk UPDATE: keeps the statement and its bindings small on a column of thousands of cards. */
    private const int CHUNK = 500;

    /**
     * Orders by a position column with NULLs FIRST on every database (MySQL, SQLite and MariaDB put them first by
     * default, PostgreSQL last), so the board, the renumbering and the lazy-column insertion all agree on one order.
     *
     * @template TModel of Model
     *
     * @param Builder<TModel> $query
     *
     * @return Builder<TModel>
     */
    public static function orderByPosition(Builder $query, string $qualifiedColumn): Builder
    {
        $wrapped = $query->getQuery()->getGrammar()->wrap($qualifiedColumn);

        // The wrapped column name comes from the model, not from user input.
        // @phpstan-ignore argument.type
        return $query->orderBy(new Expression('case when '.$wrapped.' is null then 0 else 1 end'))->orderBy($qualifiedColumn);
    }

    /**
     * @template TModel of Model
     *
     * @param Builder<TModel>   $column     Every card of the column, unfiltered by the UI (search, toggles).
     * @param array<int, mixed> $orderedIds Visible card keys in their new order.
     * @param int|string|null   $moved      A card just dropped into a LAZY column (only part of it is loaded): it is first
     *                                      placed right after the last loaded card of `$orderedIds`, so the loaded cards keep
     *                                      their places before the unloaded tail. Ties and NULL positions do not matter:
     *                                      the whole column is renumbered 1..n by its stored order.
     *
     * @return array<string, int> New position by key, only for the rows that changed.
     */
    public static function apply(Builder $column, string $sortAttribute, array $orderedIds, int|string|null $moved = null): array
    {
        $model = $column->getModel();
        $keyName = $model->getKeyName();

        $rows = self::orderByPosition((clone $column)->reorder(), $model->qualifyColumn($sortAttribute))
            ->orderBy($model->qualifyColumn($keyName))
            ->get([$model->qualifyColumn($keyName), $model->qualifyColumn($sortAttribute)]);

        $current = [];
        $keys = [];

        foreach ($rows as $row) {
            $key = (string) $row->getKey();
            $current[$key] = $row->getAttribute($sortAttribute);
            $keys[$key] = $row->getKey();
        }

        $stored = array_map('strval', array_keys($current));

        if ($moved !== null) {
            $stored = self::placeAfterLoaded($stored, (string) $moved, $orderedIds);
        }

        $order = self::slots($stored, $orderedIds);
        $changed = [];

        foreach ($order as $index => $key) {
            $position = $index + 1;

            if ($current[$key] === null || (int) $current[$key] !== $position) {
                $changed[$key] = $position;
            }
        }

        self::write($model, $sortAttribute, $changed, $keys);

        return $changed;
    }

    /**
     * Moves `$moved` to sit right behind the last card of `$loaded` that is in `$columnKeys`; a card with no loaded
     * neighbour keeps its place. The pure part of the lazy-column drop.
     *
     * @param list<string>      $columnKeys The whole column in its stored order.
     * @param array<int, mixed> $loaded     Visible keys as the browser saw them (the moved card may be among them).
     *
     * @return list<string>
     */
    public static function placeAfterLoaded(array $columnKeys, string $moved, array $loaded): array
    {
        $rest = array_values(array_filter($columnKeys, static fn (string $key): bool => $key !== $moved));
        $isLoaded = [];

        foreach ($loaded as $key) {
            if ((is_string($key) || is_int($key)) && (string) $key !== $moved) {
                $isLoaded[(string) $key] = true;
            }
        }

        $anchor = null;

        foreach ($rest as $index => $key) {
            if (isset($isLoaded[$key])) {
                $anchor = $index;
            }
        }

        if ($anchor === null) {
            return in_array($moved, $columnKeys, true) ? [...$rest, $moved] : $rest;
        }

        array_splice($rest, $anchor + 1, 0, [$moved]);

        return $rest;
    }

    /**
     * Saves the changed positions with a few CASE updates (a chunk of rows each) instead of one UPDATE per row. Writes go
     * through the connection directly: no model events, no `updated_at`.
     *
     * @param array<string, int>   $changed key => new position
     * @param array<string, mixed> $keys    key => the key as the database returned it (type preserved for the binding)
     */
    private static function write(Model $model, string $sortAttribute, array $changed, array $keys): void
    {
        if ($changed === []) {
            return;
        }

        $connection = $model->getConnection();
        $grammar = $connection->getQueryGrammar();
        $table = $grammar->wrapTable($model->getTable());
        $key = $grammar->wrap($model->getKeyName());
        $sort = $grammar->wrap($sortAttribute);

        foreach (array_chunk($changed, self::CHUNK, true) as $chunk) {
            $cases = '';
            $bindings = [];

            foreach ($chunk as $id => $position) {
                // The position is an int we computed: safe to inline (a bound text parameter would need a cast on PostgreSQL).
                $cases .= ' when ? then '.(int) $position;
                $bindings[] = $keys[(string) $id];
            }

            $in = implode(',', array_fill(0, count($chunk), '?'));

            $connection->update(
                'update '.$table.' set '.$sort.' = case '.$key.$cases.' end where '.$key.' in ('.$in.')',
                [...$bindings, ...$bindings],
            );
        }
    }

    /**
     * The pure part: the final order of a column's keys after the visible ones were rearranged.
     *
     * @param list<int|string>  $columnKeys The whole column in its stored order.
     * @param array<int, mixed> $visible    Visible keys in the new order.
     *
     * @return list<string>
     */
    public static function slots(array $columnKeys, array $visible): array
    {
        $columnKeys = array_map(static fn (int|string $key): string => (string) $key, $columnKeys);
        $known = array_flip($columnKeys);

        $queue = [];

        foreach ($visible as $key) {
            if ((is_string($key) || is_int($key)) && isset($known[(string) $key])) {
                $queue[(string) $key] = (string) $key;
            }
        }

        $queue = array_values($queue);
        $isVisible = array_flip($queue);
        $result = [];

        foreach ($columnKeys as $key) {
            $result[] = isset($isVisible[$key]) ? (string) array_shift($queue) : $key;
        }

        return $result;
    }
}
