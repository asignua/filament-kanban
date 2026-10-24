<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Tests\Fixtures;

use Illuminate\Support\Collection;

/**
 * A board that builds its cards by hand: lazy columns cannot apply to it.
 */
class LazyOverriddenBoard extends LazyTaskBoard
{
    protected function records(): Collection
    {
        return collect(parent::records());
    }
}
