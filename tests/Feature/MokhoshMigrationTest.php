<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Tests\Feature;

use Asignua\FilamentKanban\Tests\Fixtures\MokhoshStyleBoard;
use Asignua\FilamentKanban\Tests\TestCase;
use Livewire\Livewire;
use Workbench\App\Enums\TaskStatus;
use Workbench\App\Models\Task;

/**
 * The README promises that a mokhosh/filament-kanban board moves over by swapping a namespace. This is such a board.
 */
class MokhoshMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        MokhoshStyleBoard::$calls = [];
    }

    public function test_a_board_with_mokhosh_overrides_renders_its_own_records_and_statuses(): void
    {
        Task::create(['title' => 'Visible', 'status' => 'todo', 'position' => 1]);
        Task::create(['title' => 'Hidden by records()', 'status' => 'todo', 'position' => 2, 'locked' => true]);
        Task::create(['title' => 'In a column not listed', 'status' => 'done', 'position' => 1]);

        Livewire::test(MokhoshStyleBoard::class)
            ->assertSee('Visible')
            ->assertDontSee('Hidden by records()')
            ->assertDontSee('In a column not listed')
            ->assertSee('Doing');
    }

    public function test_public_hooks_are_called_with_the_mokhosh_signature(): void
    {
        $a = Task::create(['title' => 'A', 'status' => 'todo', 'position' => 1]);
        $b = Task::create(['title' => 'B', 'status' => 'doing', 'position' => 1]);

        $board = Livewire::test(MokhoshStyleBoard::class);

        $board->dispatch('status-changed', recordId: $a->id, status: 'doing', fromOrderedIds: [], toOrderedIds: [$b->id, $a->id]);
        $board->dispatch('sort-changed', recordId: $b->id, status: 'doing', orderedIds: [$b->id, $a->id]);

        $this->assertSame(TaskStatus::Doing, $a->fresh()->status);
        $this->assertSame(['status', $a->id, 'doing', [], [$b->id, $a->id]], MokhoshStyleBoard::$calls[0]);
        $this->assertSame(['sort', $b->id, 'doing', [$b->id, $a->id]], MokhoshStyleBoard::$calls[1]);
    }
}
