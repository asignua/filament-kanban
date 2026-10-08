<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Tests\Feature;

use Asignua\FilamentKanban\Tests\TestCase;
use Livewire\Livewire;
use Workbench\App\Enums\TaskStatus;
use Workbench\App\Filament\Resources\Tasks\Pages\TasksKanban;
use Workbench\App\Filament\Resources\Tasks\TaskResource;
use Workbench\App\Models\Task;

class ResourcePageTest extends TestCase
{
    public function test_a_board_can_live_on_a_resource_and_takes_the_model_from_it(): void
    {
        Task::create(['title' => 'On a resource', 'status' => 'doing']);

        Livewire::test(TasksKanban::class)->assertSee('On a resource');

        $this->assertArrayHasKey('kanban', TaskResource::getPages());
    }

    public function test_moving_works_on_a_resource_board(): void
    {
        $task = Task::create(['title' => 'A', 'status' => 'todo', 'position' => 1]);

        Livewire::test(TasksKanban::class)->call('statusChanged', $task->id, 'doing', [], [$task->id]);

        $this->assertSame(TaskStatus::Doing, $task->fresh()->status);
    }
}
