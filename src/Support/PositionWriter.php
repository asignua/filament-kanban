<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
    /**
     * @template TModel of Model
     *
     * @param Builder<TModel>   $column     Every card of the column, unfiltered by the UI (search, toggles).
     * @param array<int, mixed> $orderedIds Visible card keys in their new order.
     *
     * @return array<string, int> New position by key, only for the rows that changed.
     */
    public static function apply(Builder $column, string $sortAttribute, array $orderedIds): array
    {
        $model = $column->getModel();
        $keyName = $model->getKeyName();

        $rows = (clone $column)
            ->reorder()
            ->orderBy($model->qualifyColumn($sortAttribute))
            ->orderBy($model->qualifyColumn($keyName))
            ->get([$model->qualifyColumn($keyName), $model->qualifyColumn($sortAttribute)]);

        $current = [];

        foreach ($rows as $row) {
            $current[(string) $row->getKey()] = $row->getAttribute($sortAttribute);
        }

        $order = self::slots(array_keys($current), $orderedIds);
        $changed = [];

        foreach ($order as $index => $key) {
            $position = $index + 1;

            if ((int) $current[$key] !== $position || $current[$key] === null) {
                $changed[$key] = $position;
            }
        }

        foreach ($changed as $key => $position) {
            $model->newQueryWithoutScopes()->toBase()->where($model->qualifyColumn($keyName), $key)->update([$sortAttribute => $position]);
        }

        return $changed;
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
