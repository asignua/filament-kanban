<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Tests\Feature;

use Asignua\FilamentKanban\Tests\TestCase;
use Livewire\Livewire;
use Workbench\App\Filament\Pages\DealBoard;
use Workbench\App\Filament\Pages\TaskBoard;
use Workbench\App\Filament\Pages\TicketBoard;
use Workbench\App\Models\Deal;
use Workbench\App\Models\Stage;
use Workbench\App\Models\Task;
use Workbench\App\Models\Ticket;

class RenderingTest extends TestCase
{
    public function test_enum_columns_use_the_filament_label_color_and_icon(): void
    {
        Livewire::test(TaskBoard::class)
            ->assertSee('To do')
            ->assertSee('In progress')
            ->assertSee('Done')
            ->assertSeeHtml('data-status-id="todo"')
            ->assertSeeHtml('fi-color-warning')
            ->assertSeeHtml('fi-color-success');
    }

    public function test_cards_land_in_their_column_in_stored_order(): void
    {
        Task::create(['title' => 'Second card', 'status' => 'todo', 'position' => 2]);
        Task::create(['title' => 'First card', 'status' => 'todo', 'position' => 1]);
        Task::create(['title' => 'Elsewhere', 'status' => 'doing', 'position' => 1]);

        Livewire::test(TaskBoard::class)
            ->assertSeeInOrder(['First card', 'Second card', 'In progress', 'Elsewhere']);
    }

    public function test_every_column_has_a_drop_hint_for_when_it_is_empty(): void
    {
        Livewire::test(TaskBoard::class)
            ->assertSee(__('filament-kanban::filament-kanban.drop_here'));
    }

    public function test_the_search_narrows_the_cards_and_is_debounced_in_the_markup(): void
    {
        Task::create(['title' => 'Write docs', 'status' => 'todo']);
        Task::create(['title' => 'Fix bug', 'status' => 'todo']);

        Livewire::test(TaskBoard::class)
            ->assertSeeHtml('wire:model.live.debounce.400ms="search"')
            ->set('search', 'docs')
            ->assertSee('Write docs')
            ->assertDontSee('Fix bug')
            ->set('search', '')
            ->assertSee('Fix bug');
    }

    public function test_a_locked_card_is_marked_and_a_badge_and_header_extra_are_rendered(): void
    {
        Task::create(['title' => 'Pinned', 'status' => 'todo', 'locked' => true]);
        Task::create(['title' => 'Free', 'status' => 'todo']);

        Livewire::test(TaskBoard::class)
            ->assertSeeHtml('data-locked="true"')
            ->assertSee('Locked')
            ->assertSee('limit todo')
            ->assertSee(__('filament-kanban::filament-kanban.card_locked'));
    }

    public function test_slot_markup_is_escaped_unless_it_is_htmlable(): void
    {
        $board = new class extends TaskBoard
        {
            protected function toolbarExtras(): array
            {
                return ['<b>raw</b>', new \Illuminate\Support\HtmlString('<i>ok</i>')];
            }
        };

        $html = (fn (): string => $this->renderKanbanSlot($this->toolbarExtras()))->call($board);

        $this->assertSame('&lt;b&gt;raw&lt;/b&gt;<i>ok</i>', $html);
    }

    public function test_status_arrays_can_carry_collapsed_color_and_values(): void
    {
        Ticket::create(['title' => 'T', 'status' => 'closed']);

        Livewire::test(TicketBoard::class)
            ->assertSee('New')
            ->assertSee('Closed')
            ->assertSeeHtml('data-status-id="closed"');
    }

    public function test_a_card_with_a_url_renders_a_link_instead_of_the_edit_button(): void
    {
        $stage = Stage::create(['name' => 'S']);
        $deal = Deal::create(['title' => 'Linked', 'stage_id' => $stage->id]);

        Livewire::test(DealBoard::class)
            ->assertSeeHtml('href="/admin/deals/'.$deal->id.'"');
    }

    public function test_the_recently_updated_card_flashes(): void
    {
        Task::create(['title' => 'Fresh', 'status' => 'todo']);

        Livewire::test(TaskBoard::class)->assertSeeHtml('fi-kanban-card--flash');

        $this->travel(1)->hour();

        Livewire::test(TaskBoard::class)->assertDontSeeHtml('fi-kanban-card--flash');
    }

    public function test_the_view_data_keeps_the_mokhosh_names(): void
    {
        Task::create(['title' => 'Legacy', 'status' => 'todo']);

        Livewire::test(TaskBoard::class)->assertViewHas('statuses', function ($statuses): bool {
            $first = $statuses->first();

            return $first['id'] === 'todo' && $first['title'] === 'To do' && $first['records']->count() === 1;
        });
    }
}
