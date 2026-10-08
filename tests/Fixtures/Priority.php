<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Tests\Fixtures;

use Filament\Support\Contracts\HasLabel;

/**
 * An int-backed status enum WITHOUT the IsKanbanStatus trait (Filament HasLabel only).
 */
enum Priority: int implements HasLabel
{
    case Low = 1;
    case High = 2;

    public function getLabel(): string
    {
        return $this === self::Low ? 'Low priority' : 'High priority';
    }
}
