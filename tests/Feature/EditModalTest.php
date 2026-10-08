<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Tests\Feature;

use Asignua\FilamentKanban\Tests\TestCase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Workbench\App\Filament\Pages\DealBoard;
use Workbench\App\Filament\Pages\TaskBoard;
use Workbench\App\Filament\Pages\TicketBoard;
use Workbench\App\Models\Deal;
use Workbench\App\Models\Stage;
use Workbench\App\Models\Task;
use Workbench\App\Models\Ticket;
use Workbench\App\Policies\TicketPolicy;

class EditModalTest extends TestCase
{
    public function test_clicking_a_card_opens_a_prefilled_form_and_saving_updates_the_record(): void
    {
        $task = Task::create(['title' => 'Old title', 'status' => 'todo']);

        Livewire::test(TaskBoard::class)
            ->mountAction('kanbanEdit', ['record' => (string) $task->id])
            ->assertActionMounted('kanbanEdit')
            ->assertSchemaStateSet(['title' => 'Old title'])
            ->setActionData(['title' => 'New title'])->callMountedAction();

        $this->assertSame('New title', $task->fresh()->title);
    }

    public function test_the_modal_can_be_disabled(): void
    {
        $task = Task::create(['title' => 'A', 'status' => 'todo']);

        $board = new class extends TaskBoard
        {
            public bool $disableEditModal = true;
        };

        Livewire::test($board::class)
            ->mountAction('kanbanEdit', ['record' => (string) $task->id])
            ->assertActionNotMounted();
    }

    public function test_the_edit_is_refused_when_the_policy_says_no(): void
    {
        Gate::policy(Ticket::class, TicketPolicy::class);
        $ticket = Ticket::create(['title' => 'Forbidden', 'status' => 'new']);

        Livewire::test(TicketBoard::class)
            ->mountAction('kanbanEdit', ['record' => $ticket->id])
            ->assertActionNotMounted();

        $this->assertSame('Forbidden', $ticket->fresh()->title);
    }

    public function test_a_card_with_a_url_has_no_edit_button(): void
    {
        $stage = Stage::create(['name' => 'S']);
        Deal::create(['title' => 'Linked', 'stage_id' => $stage->id]);

        Livewire::test(DealBoard::class)
            ->assertSeeHtml('fi-kanban-card-link')
            ->assertDontSeeHtml('x-on:click="open(');
    }
}
