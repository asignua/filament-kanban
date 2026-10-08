<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Tests\Feature;

use Asignua\FilamentKanban\Events\KanbanRecordMoved;
use Asignua\FilamentKanban\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Workbench\App\Enums\TaskStatus;
use Workbench\App\Filament\Pages\TaskBoard;
use Workbench\App\Models\Task;

class TransitionTest extends TestCase
{
    public function test_a_column_with_a_transition_schema_opens_the_modal_instead_of_moving(): void
    {
        $task = Task::create(['title' => 'A', 'status' => 'doing', 'position' => 1]);

        Livewire::test(TaskBoard::class)
            ->call('statusChanged', $task->id, 'done', [], [$task->id])
            ->assertActionMounted('kanbanTransition');

        $this->assertSame(TaskStatus::Doing, $task->fresh()->status);
    }

    public function test_confirming_applies_the_move_and_saves_the_form_data(): void
    {
        Event::fake([KanbanRecordMoved::class]);

        $task = Task::create(['title' => 'A', 'status' => 'doing', 'position' => 1]);

        Livewire::test(TaskBoard::class)
            ->call('statusChanged', $task->id, 'done', [], [$task->id])
            ->setActionData(['reason' => 'shipped'])->callMountedAction()
            ->assertHasNoFormErrors();

        $task->refresh();
        $this->assertSame(TaskStatus::Done, $task->status);
        $this->assertSame('shipped', $task->reason);
        Event::assertDispatched(KanbanRecordMoved::class, fn (KanbanRecordMoved $e): bool => $e->data === ['reason' => 'shipped']);
    }

    public function test_the_form_is_validated_before_anything_moves(): void
    {
        $task = Task::create(['title' => 'A', 'status' => 'doing']);

        Livewire::test(TaskBoard::class)
            ->call('statusChanged', $task->id, 'done', [], [$task->id])
            ->callMountedAction()
            ->assertHasFormErrors(['reason' => 'required']);

        $this->assertSame(TaskStatus::Doing, $task->fresh()->status);
    }

    public function test_cancelling_leaves_the_card_where_it_was(): void
    {
        $task = Task::create(['title' => 'A', 'status' => 'doing']);

        Livewire::test(TaskBoard::class)
            ->call('statusChanged', $task->id, 'done', [], [$task->id])
            ->call('unmountAction')
            ->assertActionNotMounted();

        $this->assertSame(TaskStatus::Doing, $task->fresh()->status);
    }

    public function test_a_column_without_a_schema_moves_straight_away(): void
    {
        $task = Task::create(['title' => 'A', 'status' => 'todo']);

        Livewire::test(TaskBoard::class)
            ->call('statusChanged', $task->id, 'doing', [], [$task->id])
            ->assertActionNotMounted();

        $this->assertSame(TaskStatus::Doing, $task->fresh()->status);
    }

    public function test_a_card_locked_while_the_form_was_open_is_not_moved_on_confirm(): void
    {
        $task = Task::create(['title' => 'A', 'status' => 'doing']);

        $test = Livewire::test(TaskBoard::class)->call('statusChanged', $task->id, 'done', [], [$task->id]);

        $task->forceFill(['locked' => true])->save();

        $test->setActionData(['reason' => 'late'])->callMountedAction();

        $this->assertSame(TaskStatus::Doing, $task->fresh()->status);
    }
}
