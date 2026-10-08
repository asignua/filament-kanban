<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Tests\Fixtures;

/**
 * A pure (non-backed) enum: the column value is the case name.
 */
enum Phase
{
    case Draft;
    case Live;
}
