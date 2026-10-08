<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\TaskStatus;

/**
 * @property int $id
 * @property string $title
 * @property TaskStatus $status
 * @property int|null $position
 * @property bool $locked
 * @property string|null $reason
 */
class Task extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => TaskStatus::class, 'locked' => 'boolean', 'position' => 'integer'];
    }
}
