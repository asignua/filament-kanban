<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A model with a ULID primary key and a plain string status.
 *
 * @property string $id
 * @property string $title
 * @property string $status
 * @property int|null $sort
 */
class Ticket extends Model
{
    use HasUlids;

    protected $guarded = [];
}
