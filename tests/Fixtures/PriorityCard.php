<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 * @property Priority $priority
 * @property string $phase
 * @property int|null $position
 */
class PriorityCard extends Model
{
    protected $table = 'review_priority_cards';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['priority' => Priority::class, 'position' => 'integer'];
    }
}
