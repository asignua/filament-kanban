<?php

declare(strict_types=1);

namespace Workbench\App\Enums;

use Asignua\FilamentKanban\Concerns\IsKanbanStatus;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TaskStatus: string implements HasColor, HasIcon, HasLabel
{
    use IsKanbanStatus;

    case Todo = 'todo';
    case Doing = 'doing';
    case Done = 'done';

    public function getLabel(): string
    {
        return match ($this) {
            self::Todo => 'To do',
            self::Doing => 'In progress',
            self::Done => 'Done',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Todo => 'gray',
            self::Doing => 'warning',
            self::Done => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Todo => 'heroicon-m-inbox',
            self::Doing => 'heroicon-m-bolt',
            self::Done => 'heroicon-m-check-circle',
        };
    }
}
