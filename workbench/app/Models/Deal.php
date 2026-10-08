<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 * @property int $stage_id
 * @property int|null $sort
 */
class Deal extends Model
{
    protected $guarded = [];
}
