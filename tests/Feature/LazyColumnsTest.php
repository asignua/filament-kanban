<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Tests\Feature;

use Asignua\FilamentKanban\Support\KanbanColumn;
use Asignua\FilamentKanban\Tests\Fixtures\LazyOverriddenBoard;
use Asignua\FilamentKanban\Tests\Fixtures\LazyTaskBoard;
use Asignua\FilamentKanban\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Workbench\App\Enums\TaskStatus;
use Workbench\App\Filament\Pages\TaskBoard;
use Workbench\App\Models\Task;

class LazyColumnsTest extends TestCase
{
    protected function tearDown(): void
    {
        LazyTaskBoard::$onlyColumn = null;

        parent::tearDown();
    }

    /**
     * @return list<Task>
     */
    private function cards(string $status, int $count, string $prefix = 'Card'): array
    {
        $cards = [];

        for ($i = 1; $i <= $count; $i++) {
            $cards[] = Task::create(['title' => $prefix.' '.$status.' '.$i, 'status' => $status, 'position' => $i]);
        }

        return $cards;
    }

    private function column(object $component, string $id): KanbanColumn
    {
        /** @var KanbanColumn $column */
        $column = $component->viewData('columns')->firstWhere('id', $id);

        return $column;
    }

    public function test_a_board_without_a_page_size_loads_every_card(): void
    {
        $this->cards('todo', 7);

        $component = Livewire::test(TaskBoard::class);
        $column = $this->column($component, 'todo');

        $this->assertCount(7, $column->records);
        $this->assertFalse($column->isPaginated());
        $this->assertSame(7, $column->total());
        $component->assertDontSee(__('filament-kanban::filament-kanban.hidden_count', ['hidden' => 5]));
    }

    public function test_every_column_loads_one_page_and_the_badge_shows_the_total(): void
    {
        $this->cards('todo', 5);
        $this->cards('doing', 1);

        $component = Livewire::test(LazyTaskBoard::class);

        $todo = $this->column($component, 'todo');
        $this->assertSame(['Card todo 1', 'Card todo 2'], $todo->records->pluck('title')->all());
        $this->assertSame(5, $todo->total());
        $this->assertSame(3, $todo->hiddenCount());

        $this->assertSame(1, $this->column($component, 'doing')->total());
        $this->assertSame(0, $this->column($component, 'doing')->hiddenCount());

        $component->assertSee(__('filament-kanban::filament-kanban.show_more', ['count' => 2]));
    }

    public function test_the_default_path_issues_no_count_query_and_the_lazy_path_one_per_column(): void
    {
        $this->cards('todo', 5);

        DB::enableQueryLog();
        Livewire::test(TaskBoard::class)->viewData('columns');
        $plain = collect(DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], 'count(*)'))->count();
        DB::flushQueryLog();

        $this->assertSame(0, $plain);

        DB::flushQueryLog();
        Livewire::test(LazyTaskBoard::class)->viewData('columns');
        $tasks = collect(DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], 'from "tasks"'));

        // Three lazy columns: three pages + one grouped count.
        $this->assertCount(4, $tasks);
    }

    public function test_show_more_adds_one_page_and_stops_when_everything_is_loaded(): void
    {
        $this->cards('todo', 5);

        $component = Livewire::test(LazyTaskBoard::class)->call('loadMoreRecords', 'todo');
        $this->assertCount(4, $this->column($component, 'todo')->records);

        $component->call('loadMoreRecords', 'todo');
        $column = $this->column($component, 'todo');
        $this->assertCount(5, $column->records);
        $this->assertSame(0, $column->hiddenCount());
        $component->assertDontSee(__('filament-kanban::filament-kanban.show_more', ['count' => 1]));
    }

    public function test_the_button_offers_the_remainder_when_it_is_smaller_than_a_page(): void
    {
        $this->cards('todo', 3);

        Livewire::test(LazyTaskBoard::class)->assertSee(__('filament-kanban::filament-kanban.show_more', ['count' => 1]));
    }

    public function test_search_narrows_the_loaded_cards_and_the_totals(): void
    {
        $this->cards('todo', 4, 'Alpha');
        $this->cards('todo', 3, 'Beta');

        $component = Livewire::test(LazyTaskBoard::class)->set('search', 'Beta');
        $column = $this->column($component, 'todo');

        $this->assertSame(3, $column->total());
        $this->assertCount(2, $column->records);
        $this->assertTrue($column->records->every(fn (Task $task): bool => str_contains($task->title, 'Beta')));
    }

    public function test_only_the_columns_that_opt_in_paginate(): void
    {
        LazyTaskBoard::$onlyColumn = 'done';

        $this->cards('todo', 5);
        $this->cards('done', 5);

        $component = Livewire::test(LazyTaskBoard::class);

        $this->assertCount(5, $this->column($component, 'todo')->records);
        $this->assertFalse($this->column($component, 'todo')->isPaginated());
        $this->assertCount(2, $this->column($component, 'done')->records);
        $this->assertSame(5, $this->column($component, 'done')->total());

        // A column that does not paginate has nothing to extend.
        $component->call('loadMoreRecords', 'todo');
        $this->assertSame([], $component->get('kanbanColumnLimits'));
    }

    public function test_an_unknown_column_is_refused(): void
    {
        $this->cards('todo', 5);

        $component = Livewire::test(LazyTaskBoard::class)->call('loadMoreRecords', 'nope');

        $this->assertSame([], $component->get('kanbanColumnLimits'));
    }

    public function test_a_board_that_overrides_records_loads_everything(): void
    {
        $this->cards('todo', 5);

        $component = Livewire::test(LazyOverriddenBoard::class);

        $this->assertCount(5, $this->column($component, 'todo')->records);
        $this->assertFalse($this->column($component, 'todo')->isPaginated());
    }

    public function test_reordering_loaded_cards_leaves_the_unloaded_ones_in_their_places(): void
    {
        [$a, $b] = $this->cards('todo', 5);

        Livewire::test(LazyTaskBoard::class)->call('sortChanged', $b->id, 'todo', [$b->id, $a->id]);

        $this->assertSame([2, 1], [$a->fresh()->position, $b->fresh()->position]);
        $this->assertSame([3, 4, 5], Task::query()->whereIn('title', ['Card todo 3', 'Card todo 4', 'Card todo 5'])->orderBy('position')->pluck('position')->all());
    }

    public function test_a_card_dropped_into_a_lazy_column_lands_among_the_loaded_ones_and_widens_the_page(): void
    {
        $todo = $this->cards('todo', 4);
        $moved = Task::create(['title' => 'Moved', 'status' => 'doing', 'position' => 1]);

        $component = Livewire::test(LazyTaskBoard::class);
        $loaded = $this->column($component, 'todo')->records->pluck('id')->all();
        $this->assertSame([$todo[0]->id, $todo[1]->id], $loaded);

        // Dropped at the top of the loaded page: [moved, first, second].
        $component->call('statusChanged', $moved->id, 'todo', [], [$moved->id, $todo[0]->id, $todo[1]->id]);

        $this->assertSame(TaskStatus::Todo, $moved->fresh()->status);
        $this->assertSame([1, 2, 3, 4, 5], [
            $moved->fresh()->position,
            $todo[0]->fresh()->position,
            $todo[1]->fresh()->position,
            $todo[2]->fresh()->position,
            $todo[3]->fresh()->position,
        ]);

        // The page grew by one: nothing that was on screen is gone, the badge counts the new card.
        $column = $this->column($component, 'todo');
        $this->assertSame(['Moved', 'Card todo 1', 'Card todo 2'], $column->records->pluck('title')->all());
        $this->assertSame(5, $column->total());
        $this->assertSame(3, $component->get('kanbanColumnLimits')['todo']);
    }

    public function test_a_move_out_of_a_lazy_column_updates_both_totals(): void
    {
        $todo = $this->cards('todo', 4);

        $component = Livewire::test(LazyTaskBoard::class)
            ->call('statusChanged', $todo[0]->id, 'doing', [], [$todo[0]->id]);

        $this->assertSame(3, $this->column($component, 'todo')->total());
        $this->assertSame(1, $this->column($component, 'doing')->total());
    }
}
