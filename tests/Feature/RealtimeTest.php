<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Tests\Feature;

use Asignua\FilamentKanban\Events\KanbanBoardChanged;
use Asignua\FilamentKanban\KanbanServiceProvider;
use Asignua\FilamentKanban\Support\Realtime;
use Asignua\FilamentKanban\Tests\Fixtures\DenyViewAnyPolicy;
use Asignua\FilamentKanban\Tests\TestCase;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use ReflectionMethod;
use Workbench\App\Filament\Pages\TaskBoard;
use Workbench\App\Filament\Pages\TicketBoard;
use Workbench\App\Models\Task;
use Workbench\App\Models\Ticket;
use Workbench\App\Models\User;
use Workbench\App\Policies\TicketPolicy;

class RealtimeTest extends TestCase
{
    protected function tearDown(): void
    {
        Realtime::authorizeUsing(null);

        parent::tearDown();
    }

    private function enable(bool $on = true): void
    {
        config(['filament-kanban.realtime.enabled' => $on]);
    }

    public function test_a_move_is_broadcast_after_it_is_written_when_realtime_is_on(): void
    {
        $this->enable();
        Event::fake([KanbanBoardChanged::class]);
        $task = Task::create(['title' => 'A', 'status' => 'todo', 'position' => 1]);

        $board = Livewire::test(TaskBoard::class)->call('statusChanged', $task->id, 'doing', [], [$task->id]);

        Event::assertDispatched(KanbanBoardChanged::class, fn (KanbanBoardChanged $event): bool => $event->board === TaskBoard::class
            && $event->record === (string) $task->id
            && $event->type === KanbanBoardChanged::MOVED
            && $event->origin === $board->id());
    }

    public function test_a_reorder_is_broadcast_as_sorted(): void
    {
        $this->enable();
        Event::fake([KanbanBoardChanged::class]);
        $a = Task::create(['title' => 'A', 'status' => 'todo', 'position' => 1]);
        $b = Task::create(['title' => 'B', 'status' => 'todo', 'position' => 2]);

        Livewire::test(TaskBoard::class)->call('sortChanged', $b->id, 'todo', [$b->id, $a->id]);

        Event::assertDispatched(KanbanBoardChanged::class, fn (KanbanBoardChanged $event): bool => $event->type === KanbanBoardChanged::SORTED);
    }

    public function test_nothing_is_broadcast_when_realtime_is_off(): void
    {
        $this->enable(false);
        Event::fake([KanbanBoardChanged::class]);
        $task = Task::create(['title' => 'A', 'status' => 'todo', 'position' => 1]);

        Livewire::test(TaskBoard::class)->call('statusChanged', $task->id, 'doing', [], [$task->id]);

        Event::assertNotDispatched(KanbanBoardChanged::class);
    }

    public function test_a_denied_move_is_not_broadcast(): void
    {
        $this->enable();
        Gate::policy(Ticket::class, TicketPolicy::class);
        Event::fake([KanbanBoardChanged::class]);
        $ticket = Ticket::create(['title' => 'Forbidden', 'status' => 'new', 'sort' => 1]);

        Livewire::test(TicketBoard::class)->call('statusChanged', $ticket->id, 'open', [], [$ticket->id]);

        Event::assertNotDispatched(KanbanBoardChanged::class);
    }

    public function test_a_saved_edit_is_broadcast_as_updated(): void
    {
        $this->enable();
        Event::fake([KanbanBoardChanged::class]);
        $task = Task::create(['title' => 'A', 'status' => 'todo', 'position' => 1]);

        Livewire::test(TaskBoard::class)
            ->callAction('kanbanEdit', ['title' => 'Renamed'], ['record' => $task->id]);

        Event::assertDispatched(KanbanBoardChanged::class, fn (KanbanBoardChanged $event): bool => $event->type === KanbanBoardChanged::UPDATED
            && $event->record === (string) $task->id);
    }

    public function test_the_event_is_a_signal_with_identifiers_only(): void
    {
        $event = new KanbanBoardChanged(TaskBoard::class, '7', KanbanBoardChanged::MOVED, 'abc');

        $this->assertInstanceOf(ShouldBroadcastNow::class, $event);
        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, $event);
        $this->assertSame(['board' => TaskBoard::class, 'record' => '7', 'type' => 'moved', 'origin' => 'abc'], $event->broadcastWith());
        $this->assertSame('filament-kanban.board-changed', $event->broadcastAs());

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-'.Realtime::channelFor(TaskBoard::class), $channels[0]->name);
    }

    public function test_the_channel_follows_the_boards_own_access_rule(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(Realtime::authorizeChannel($user, 'Workbench-App-Filament-Pages-TicketBoard'));

        Gate::policy(Ticket::class, DenyViewAnyPolicy::class);

        $this->assertFalse(TicketBoard::canAccess());
        $this->assertFalse(Realtime::authorizeChannel($user, 'Workbench-App-Filament-Pages-TicketBoard'));
        $this->assertTrue(Realtime::authorizeChannel($user, 'Workbench-App-Filament-Pages-TaskBoard'));
    }

    public function test_the_key_must_name_a_kanban_board_and_a_guest_is_refused(): void
    {
        $user = User::factory()->create();

        $this->assertFalse(Realtime::authorizeChannel($user, 'Workbench-App-Models-Task'));
        $this->assertFalse(Realtime::authorizeChannel($user, 'No-Such-Board'));
        $this->assertFalse(Realtime::authorizeChannel($user, '../../etc/passwd'));
        $this->assertFalse(Realtime::authorizeChannel(null, 'Workbench-App-Filament-Pages-TaskBoard'));
    }

    public function test_the_optional_callback_can_narrow_the_channel(): void
    {
        $user = User::factory()->create();
        $key = 'Workbench-App-Filament-Pages-TaskBoard';

        Realtime::authorizeUsing(fn ($u, string $board): bool => $board !== TaskBoard::class);

        $this->assertFalse(Realtime::authorizeChannel($user, $key));
    }

    public function test_the_provider_registers_the_channel_with_that_rule_when_realtime_is_on(): void
    {
        config(['filament-kanban.realtime.enabled' => true]);

        // The provider ran at boot with realtime off: run its registration again for this config.
        (new ReflectionMethod(KanbanServiceProvider::class, 'registerBroadcasting'))
            ->invoke(app()->getProvider(KanbanServiceProvider::class));

        /** @var array<string, callable> $channels */
        $channels = Broadcast::driver()->getChannels()->all();
        $callback = $channels[KanbanBoardChanged::CHANNEL.'{board}'] ?? null;

        $this->assertNotNull($callback);

        $user = User::factory()->create();

        $this->assertTrue($callback($user, 'Workbench-App-Filament-Pages-TaskBoard'));
        $this->assertFalse($callback($user, 'Workbench-App-Models-Task'));
    }

    public function test_an_echo_signal_from_another_copy_tells_the_browser_to_refresh(): void
    {
        $this->enable();

        Livewire::test(TaskBoard::class)
            ->call('onKanbanBoardChanged', ['board' => TaskBoard::class, 'record' => '1', 'type' => 'moved', 'origin' => 'someone-else'])
            ->assertDispatched('kanban-board-changed', busy: false);
    }

    public function test_the_signal_reports_an_open_modal_as_busy(): void
    {
        $this->enable();
        $task = Task::create(['title' => 'A', 'status' => 'todo', 'position' => 1]);

        Livewire::test(TaskBoard::class)
            ->mountAction('kanbanEdit', ['record' => $task->id])
            ->call('onKanbanBoardChanged', ['origin' => 'someone-else'])
            ->assertDispatched('kanban-board-changed', busy: true);
    }

    public function test_the_own_echo_is_ignored(): void
    {
        $this->enable();

        $board = Livewire::test(TaskBoard::class);
        $board->call('onKanbanBoardChanged', ['origin' => $board->id()])->assertNotDispatched('kanban-board-changed');
    }

    public function test_the_board_listens_on_its_channel_only_when_realtime_is_on(): void
    {
        $this->enable(false);
        $off = Livewire::test(TaskBoard::class);
        $this->assertSame([], $this->listenersOf($off->instance()));
        $off->assertDontSee('fi-kanban-notice', false)->assertDontSee('realtime:', false);

        $this->enable();
        $on = Livewire::test(TaskBoard::class);
        $this->assertSame([Realtime::listener(TaskBoard::class) => 'onKanbanBoardChanged'], $this->listenersOf($on->instance()));
        $on->assertSee('fi-kanban-notice', false)
            ->assertSee('realtime:', false)
            ->assertSee(__('filament-kanban::filament-kanban.board_changed'));
    }

    /**
     * @return array<string, string>
     */
    private function listenersOf(object $board): array
    {
        /** @var array<string, string> */
        return (new ReflectionMethod($board, 'getListeners'))->invoke($board);
    }
}
