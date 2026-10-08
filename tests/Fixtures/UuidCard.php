<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A card with a UUID primary key (review fixture; the table is created by the test).
 *
 * @property string $id
 * @property string $title
 * @property string $status
 * @property int|null $sort
 */
class UuidCard extends Model
{
    use HasUuids;

    protected $table = 'review_uuid_cards';

    protected $guarded = [];
}
