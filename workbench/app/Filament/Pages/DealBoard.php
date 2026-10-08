<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Pages;

use Asignua\FilamentKanban\Pages\KanbanBoard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Workbench\App\Models\Deal;
use Workbench\App\Models\Stage;

/**
 * Columns are rows of a table; the status attribute is a foreign key.
 */
class DealBoard extends KanbanBoard
{
    protected static string $model = Deal::class;

    protected static string $recordStatusAttribute = 'stage_id';

    protected static ?string $recordSortAttribute = 'sort';

    protected static ?string $slug = 'deals-board';

    protected function statuses(): Collection
    {
        return Stage::query()->orderBy('id')->get()->map(fn (Stage $stage): array => [
            'id' => (string) $stage->id,
            'title' => $stage->name,
            'value' => $stage->id,
            'color' => $stage->color,
        ]);
    }

    protected function recordUrl(Model $record): ?string
    {
        return '/admin/deals/'.$record->getKey();
    }

    protected function validateMove(Model $record, string $from, string $to): ?string
    {
        return $to === '99' ? 'Blocked by a rule' : null;
    }
}
