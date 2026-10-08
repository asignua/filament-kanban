<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Tasks;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Workbench\App\Filament\Resources\Tasks\Pages\ListTasks;
use Workbench\App\Filament\Resources\Tasks\Pages\TasksKanban;
use Workbench\App\Models\Task;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static ?string $slug = 'tasks';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('title')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTasks::route('/'),
            'kanban' => TasksKanban::route('/kanban'),
        ];
    }
}
