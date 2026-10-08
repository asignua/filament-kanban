<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Tests\Feature;

use Asignua\FilamentKanban\Tests\TestCase;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Livewire;
use Workbench\App\Enums\TaskStatus;
use Workbench\App\Filament\Pages\DealBoard;
use Workbench\App\Filament\Pages\TaskBoard;
use Workbench\App\Filament\Pages\TicketBoard;
use Workbench\App\Models\Deal;
use Workbench\App\Models\Stage;
use Workbench\App\Models\Task;
use Workbench\App\Models\Ticket;
use Workbench\App\Policies\TicketPolicy;

class AuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Gate::policy(Ticket::class, TicketPolicy::class);
    }

    public function test_a_denied_move_notifies_and_leaves_the_card_where_it_was(): void
    {
        $ticket = Ticket::create(['title' => 'Forbidden', 'status' => 'new', 'sort' => 1]);

        Livewire::test(TicketBoard::class)
            ->call('statusChanged', $ticket->id, 'open', [], [$ticket->id]);

        $this->assertSame('new', $ticket->fresh()->status);
        Notification::assertNotified(__('filament-kanban::filament-kanban.move_denied'));
    }

    public function test_a_denied_reorder_is_refused_too(): void
    {
        $a = Ticket::create(['title' => 'Forbidden', 'status' => 'new', 'sort' => 1]);
        $b = Ticket::create(['title' => 'Fine', 'status' => 'new', 'sort' => 2]);

        Livewire::test(TicketBoard::class)->call('sortChanged', $a->id, 'new', [$b->id, $a->id]);

        $this->assertSame(1, $a->fresh()->sort);
        Notification::assertNotified(__('filament-kanban::filament-kanban.move_denied'));
    }

    public function test_the_policy_allows_the_rest(): void
    {
        $ticket = Ticket::create(['title' => 'Fine', 'status' => 'new', 'sort' => 1]);

        Livewire::test(TicketBoard::class)->call('statusChanged', $ticket->id, 'open', [], [$ticket->id]);

        $this->assertSame('open', $ticket->fresh()->status);
    }

    public function test_without_a_policy_a_model_may_be_moved(): void
    {
        $task = Task::create(['title' => 'A', 'status' => 'todo']);

        Livewire::test(TaskBoard::class)->call('statusChanged', $task->id, 'doing', [], [$task->id]);

        $this->assertSame(TaskStatus::Doing, $task->fresh()->status);
    }

    public function test_a_locked_card_cannot_be_moved_even_by_a_forged_call(): void
    {
        $task = Task::create(['title' => 'Pinned', 'status' => 'todo', 'locked' => true]);

        Livewire::test(TaskBoard::class)
            ->call('statusChanged', $task->id, 'doing', [], [$task->id])
            ->call('sortChanged', $task->id, 'todo', [$task->id]);

        $this->assertSame(TaskStatus::Todo, $task->fresh()->status);
        Notification::assertNotified(__('filament-kanban::filament-kanban.move_denied'));
    }

    public function test_the_write_hooks_cannot_be_called_from_the_browser(): void
    {
        $task = Task::create(['title' => 'A', 'status' => 'todo']);

        try {
            Livewire::test(TaskBoard::class)->call('onStatusChanged', $task->id, 'doing', [], [$task->id]);
            $this->fail('A protected hook must not be callable from the browser.');
        } catch (MethodNotFoundException|\Throwable $exception) {
            $this->assertNotSame('A protected hook must not be callable from the browser.', $exception->getMessage());
        }

        $this->assertSame(TaskStatus::Todo, $task->fresh()->status);
    }

    public function test_a_before_move_rule_can_refuse_with_its_own_message(): void
    {
        $first = Stage::create(['name' => 'First']);
        $blocked = new Stage;
        $blocked->forceFill(['id' => 99, 'name' => 'Blocked'])->save();
        $deal = Deal::create(['title' => 'D', 'stage_id' => $first->id]);

        Livewire::test(DealBoard::class)->call('statusChanged', $deal->id, '99', [], [$deal->id]);

        $this->assertSame($first->id, $deal->fresh()->stage_id);
        Notification::assertNotified('Blocked by a rule');
    }
}
