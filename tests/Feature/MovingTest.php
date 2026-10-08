<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Tests\Feature;

use Asignua\FilamentKanban\Events\KanbanRecordMoved;
use Asignua\FilamentKanban\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Workbench\App\Enums\TaskStatus;
use Workbench\App\Filament\Pages\DealBoard;
use Workbench\App\Filament\Pages\NoteBoard;
use Workbench\App\Filament\Pages\TaskBoard;
use Workbench\App\Filament\Pages\TicketBoard;
use Workbench\App\Models\Deal;
use Workbench\App\Models\Note;
use Workbench\App\Models\Stage;
use Workbench\App\Models\Task;
use Workbench\App\Models\Ticket;

class MovingTest extends TestCase
{
    public function test_dropping_a_card_into_another_column_changes_its_status_and_numbers_the_column(): void
    {
        $a = Task::create(['title' => 'A', 'status' => 'todo', 'position' => 1]);
        $b = Task::create(['title' => 'B', 'status' => 'doing', 'position' => 1]);
        $c = Task::create(['title' => 'C', 'status' => 'doing', 'position' => 2]);

        Livewire::test(TaskBoard::class)
            ->call('statusChanged', $a->id, 'doing', [], [$b->id, $a->id, $c->id]);

        $this->assertSame(TaskStatus::Doing, $a->fresh()->status);
        $this->assertSame([1, 2, 3], [$b->fresh()->position, $a->fresh()->position, $c->fresh()->position]);
    }

    public function test_the_ids_may_arrive_as_strings_from_the_dom(): void
    {
        $a = Task::create(['title' => 'A', 'status' => 'todo', 'position' => 1]);
        $b = Task::create(['title' => 'B', 'status' => 'todo', 'position' => 2]);

        Livewire::test(TaskBoard::class)
            ->call('sortChanged', (string) $b->id, 'todo', [(string) $b->id, (string) $a->id]);

        $this->assertSame(1, $b->fresh()->position);
        $this->assertSame(2, $a->fresh()->position);
    }

    public function test_reordering_inside_a_column_only_renumbers(): void
    {
        $a = Task::create(['title' => 'A', 'status' => 'todo', 'position' => 1]);
        $b = Task::create(['title' => 'B', 'status' => 'todo', 'position' => 2]);
        $updated = $a->updated_at;

        $this->travel(1)->minute();

        Livewire::test(TaskBoard::class)->call('sortChanged', $b->id, 'todo', [$b->id, $a->id]);

        $this->assertSame([2, 1], [$a->fresh()->position, $b->fresh()->position]);
        // Renumbering must not touch updated_at (it would make every card flash).
        $this->assertEquals($updated, $a->fresh()->updated_at);
    }

    public function test_only_visible_cards_trade_places_hidden_ones_keep_theirs(): void
    {
        $one = Task::create(['title' => 'Alpha one', 'status' => 'todo', 'position' => 1]);
        $hidden = Task::create(['title' => 'Hidden', 'status' => 'todo', 'position' => 2]);
        $two = Task::create(['title' => 'Alpha two', 'status' => 'todo', 'position' => 3]);

        Livewire::test(TaskBoard::class)
            ->set('search', 'Alpha')
            ->assertDontSee('Hidden')
            ->call('sortChanged', $two->id, 'todo', [$two->id, $one->id]);

        $this->assertSame(1, $two->fresh()->position);
        $this->assertSame(2, $hidden->fresh()->position);
        $this->assertSame(3, $one->fresh()->position);
    }

    public function test_a_move_into_a_column_while_searching_keeps_hidden_cards_in_place(): void
    {
        $moved = Task::create(['title' => 'Alpha', 'status' => 'todo', 'position' => 1]);
        $x = Task::create(['title' => 'Beta', 'status' => 'doing', 'position' => 1]);
        $y = Task::create(['title' => 'Alpha too', 'status' => 'doing', 'position' => 2]);

        Livewire::test(TaskBoard::class)
            ->set('search', 'Alpha')
            ->call('statusChanged', $moved->id, 'doing', [], [$moved->id, $y->id]);

        $this->assertSame(TaskStatus::Doing, $moved->fresh()->status);
        // Column order after the move: moved, Beta (hidden), Alpha too. Beta keeps the middle slot.
        $this->assertSame([1, 2, 3], [$moved->fresh()->position, $x->fresh()->position, $y->fresh()->position]);
    }

    public function test_a_move_fires_an_event_and_a_reorder_does_not(): void
    {
        Event::fake([KanbanRecordMoved::class]);

        $a = Task::create(['title' => 'A', 'status' => 'todo', 'position' => 1]);

        Livewire::test(TaskBoard::class)
            ->call('sortChanged', $a->id, 'todo', [$a->id])
            ->call('statusChanged', $a->id, 'doing', [], [$a->id]);

        Event::assertDispatchedTimes(KanbanRecordMoved::class, 1);
        Event::assertDispatched(KanbanRecordMoved::class, fn (KanbanRecordMoved $e): bool => $e->from === 'todo' && $e->to === 'doing' && $e->board === TaskBoard::class);
    }

    public function test_ulid_keys_work(): void
    {
        $a = Ticket::create(['title' => 'A', 'status' => 'new', 'sort' => 1]);
        $b = Ticket::create(['title' => 'B', 'status' => 'open', 'sort' => 1]);

        $this->assertSame(26, strlen((string) $a->id));

        Livewire::test(TicketBoard::class)
            ->call('statusChanged', $a->id, 'open', [], [$a->id, $b->id]);

        $this->assertSame('open', $a->fresh()->status);
        $this->assertSame(1, $a->fresh()->sort);
        $this->assertSame(2, $b->fresh()->sort);
    }

    public function test_columns_from_database_rows_write_the_foreign_key(): void
    {
        $first = Stage::create(['name' => 'First']);
        $second = Stage::create(['name' => 'Second', 'color' => 'success']);
        $deal = Deal::create(['title' => 'Big deal', 'stage_id' => $first->id, 'sort' => 1]);

        Livewire::test(DealBoard::class)
            ->assertSee('First')
            ->assertSee('Second')
            ->call('statusChanged', $deal->id, (string) $second->id, [], [$deal->id]);

        $this->assertSame($second->id, $deal->fresh()->stage_id);
    }

    public function test_an_unknown_column_is_refused(): void
    {
        $a = Task::create(['title' => 'A', 'status' => 'todo', 'position' => 1]);

        Livewire::test(TaskBoard::class)->call('statusChanged', $a->id, 'nope', [], [$a->id]);

        $this->assertSame(TaskStatus::Todo, $a->fresh()->status);
    }

    public function test_a_vanished_record_does_not_break_the_board(): void
    {
        Livewire::test(TaskBoard::class)
            ->call('statusChanged', 999, 'doing', [], [999])
            ->assertOk();
    }

    public function test_without_a_sort_attribute_the_model_order_hook_is_used(): void
    {
        Note::$orders = [];
        $a = Note::create(['title' => 'A', 'status' => 'open', 'order_column' => 1]);
        $b = Note::create(['title' => 'B', 'status' => 'open', 'order_column' => 2]);

        Livewire::test(NoteBoard::class)
            ->call('sortChanged', $b->id, 'open', [$b->id, $a->id]);

        $this->assertSame([[$b->id, $a->id]], Note::$orders);
        $this->assertSame(1, $b->fresh()->order_column);
    }
}
