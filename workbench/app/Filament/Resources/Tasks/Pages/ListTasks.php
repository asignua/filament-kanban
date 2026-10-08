<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Tasks\Pages;

use Filament\Resources\Pages\ListRecords;
use Workbench\App\Filament\Resources\Tasks\TaskResource;

class ListTasks extends ListRecords
{
    protected static string $resource = TaskResource::class;
}
