<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Pages;

use Asignua\FilamentKanban\Concerns\InteractsWithKanban;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A Kanban page that lives on a resource: register it in `getPages()` (`'kanban' => KanbanPage::route('/kanban')`).
 * The model and the base query (tenancy, soft deletes...) come from the resource; `$model` is optional.
 */
class KanbanResourcePage extends Page
{
    use InteractsWithKanban;

    protected string $view = 'filament-kanban::kanban-board';

    /**
     * @return class-string<Model>
     */
    protected static function kanbanModel(): string
    {
        return isset(static::$model) ? static::$model : static::getResource()::getModel();
    }

    /**
     * @return Builder<Model>
     */
    protected function getEloquentQuery(): Builder
    {
        return static::getResource()::getEloquentQuery();
    }
}
