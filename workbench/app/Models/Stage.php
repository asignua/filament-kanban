<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Columns that live in the database.
 *
 * @property int $id
 * @property string $name
 * @property string|null $color
 */
class Stage extends Model
{
    protected $guarded = [];
}
