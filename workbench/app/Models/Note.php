<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Imitates a spatie/eloquent-sortable model (`ordered()` scope + static `setNewOrder()`) without the package.
 *
 * @property int $id
 * @property string $title
 * @property string $status
 * @property int|null $order_column
 */
class Note extends Model
{
    /** @var list<array<int, int|string>> */
    public static array $orders = [];

    protected $guarded = [];

    /**
     * @param Builder<Note> $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('order_column');
    }

    /**
     * @param array<int, int|string> $ids
     */
    public static function setNewOrder(array $ids): void
    {
        self::$orders[] = $ids;

        foreach (array_values($ids) as $index => $id) {
            static::query()->whereKey($id)->toBase()->update(['order_column' => $index + 1]);
        }
    }
}
