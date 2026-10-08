<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Tests\Fixtures;

use Asignua\FilamentKanban\Pages\KanbanBoard;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Collection;

class UuidCardBoard extends KanbanBoard
{
    protected static string $model = UuidCard::class;

    protected static ?string $recordSortAttribute = 'sort';

    protected function statuses(): Collection
    {
        return collect([['id' => 'a', 'title' => 'Column A'], ['id' => 'b', 'title' => 'Column B']]);
    }

    protected function columnTransitionSchema(string $from, string $to): ?array
    {
        return $to === 'b' ? [TextInput::make('title')->required()] : null;
    }
}
