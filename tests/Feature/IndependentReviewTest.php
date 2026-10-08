<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Tests\Feature;

use Asignua\FilamentKanban\Support\KanbanColumn;
use Asignua\FilamentKanban\Tests\Fixtures\CustomStatusesEnum;
use Asignua\FilamentKanban\Tests\Fixtures\Phase;
use Asignua\FilamentKanban\Tests\Fixtures\PhaseBoard;
use Asignua\FilamentKanban\Tests\Fixtures\Priority;
use Asignua\FilamentKanban\Tests\Fixtures\PriorityBoard;
use Asignua\FilamentKanban\Tests\Fixtures\PriorityCard;
use Asignua\FilamentKanban\Tests\Fixtures\UuidCard;
use Asignua\FilamentKanban\Tests\Fixtures\UuidCardBoard;
use Asignua\FilamentKanban\Tests\TestCase;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Throwable;
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
use Workbench\App\Policies\TicketPolicy;

/**
 * Independent review tests (2026-10-08). Tests marked "BUG" in their docblock fail on purpose: they describe the
 * behaviour the plugin should have and expose a defect in the current code.
 */
class IndependentReviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('review_uuid_cards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('status')->default('a');
            $table->unsignedInteger('sort')->nullable();
            $table->timestamps();
        });

        Schema::create('review_priority_cards', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->unsignedTinyInteger('priority')->default(1);
            $table->string('phase')->default('Draft');
            $table->unsignedInteger('position')->nullable();
            $table->timestamps();
        });
    }

    // ---------------------------------------------------------------- security

    /**
     * BUG: the transition form is chosen from the `from` ARGUMENT sent by the browser, not from the record's real
     * status. A forged `mountAction('kanbanTransition', {record, from: <a column without a form>, to})` moves the card
     * without the required transition data.
     */
    public function test_a_forged_from_argument_cannot_skip_a_required_transition_form(): void
    {
        $board = new class extends TaskBoard
        {
            protected function columnTransitionSchema(string $from, string $to): ?array
            {
                return $from === 'doing' && $to === 'done' ? [TextInput::make('reason')->required()] : null;
            }
        };

        $task = Task::create(['title' => 'A', 'status' => 'doing', 'position' => 1]);

        Livewire::test($board::class)
            ->mountAction('kanbanTransition', ['record' => $task->id, 'from' => 'todo', 'to' => 'done'])
            ->callMountedAction();

        $task->refresh();
        $this->assertTrue(
            $task->status === TaskStatus::Doing || $task->reason !== null,
            'The card reached "done" without the required reason: the form was picked from the forged `from`.',
        );
    }

    /**
     * BUG: `$disableEditModal` is a public, unlocked Livewire property. A board that turns the modal off can have it
     * switched back on from the browser (`$wire.set('disableEditModal', false)`) and the record edited.
     */
    public function test_a_disabled_edit_modal_cannot_be_re_enabled_from_the_browser(): void
    {
        $task = Task::create(['title' => 'Original', 'status' => 'todo']);

        $board = new class extends TaskBoard
        {
            public bool $disableEditModal = true;
        };

        try {
            Livewire::test($board::class)
                ->set('disableEditModal', false)
                ->mountAction('kanbanEdit', ['record' => (string) $task->id])
                ->setActionData(['title' => 'Hacked'])
                ->callMountedAction();
        } catch (Throwable) {
            // A locked property throws: that is the fix.
        }

        $this->assertSame('Original', $task->fresh()->title);
    }

    /**
     * BUG: a board whose cards are links (recordUrl) has no edit button, but the edit action stays mountable, so a
     * forged `mountAction('kanbanEdit', {record})` edits the record through the default title field.
     */
    public function test_a_board_with_card_urls_does_not_expose_the_edit_action(): void
    {
        $stage = Stage::create(['name' => 'S']);
        $deal = Deal::create(['title' => 'Original', 'stage_id' => $stage->id]);

        try {
            Livewire::test(DealBoard::class)
                ->mountAction('kanbanEdit', ['record' => (string) $deal->id])
                ->setActionData(['title' => 'Hacked'])
                ->callMountedAction();
        } catch (Throwable) {
        }

        $this->assertSame('Original', $deal->fresh()->title);
    }

    public function test_records_outside_the_base_query_cannot_be_moved_edited_or_transitioned(): void
    {
        $board = new class extends TaskBoard
        {
            protected function getEloquentQuery(): Builder
            {
                return Task::query()->where('title', '!=', 'Secret');
            }
        };

        $secret = Task::create(['title' => 'Secret', 'status' => 'todo', 'position' => 1]);

        Livewire::test($board::class)
            ->assertDontSee('Secret')
            ->call('statusChanged', $secret->id, 'doing', [], [$secret->id])
            ->call('statusChanged', $secret->id, 'done', [], [$secret->id])
            ->assertActionNotMounted('kanbanTransition')
            ->mountAction('kanbanEdit', ['record' => (string) $secret->id])
            ->assertActionNotMounted('kanbanEdit');

        $this->assertSame(TaskStatus::Todo, $secret->fresh()->status);
        $this->assertSame('Secret', $secret->fresh()->title);
    }

    public function test_a_forged_transition_mount_still_runs_the_policy(): void
    {
        Gate::policy(Ticket::class, TicketPolicy::class);
        $ticket = Ticket::create(['title' => 'Forbidden', 'status' => 'new', 'sort' => 1]);

        Livewire::test(TicketBoard::class)
            ->mountAction('kanbanTransition', ['record' => $ticket->id, 'from' => 'new', 'to' => 'open'])
            ->callMountedAction();

        $this->assertSame('new', $ticket->fresh()->status);
    }

    public function test_ids_of_another_column_in_a_reorder_are_ignored(): void
    {
        $a = Task::create(['title' => 'A', 'status' => 'todo', 'position' => 1]);
        $other = Task::create(['title' => 'Other', 'status' => 'doing', 'position' => 7]);

        Livewire::test(TaskBoard::class)
            ->call('sortChanged', $a->id, 'todo', [$other->id, $a->id, 'garbage', null, ['nested']]);

        $this->assertSame(7, $other->fresh()->position);
        $this->assertSame(1, $a->fresh()->position);
    }

    /**
     * BUG (spatie/eloquent-sortable path): the browser's ids go straight to `setNewOrder()`, unchecked. Ids of cards in
     * another column (or not on the board at all) are renumbered.
     */
    public function test_the_set_new_order_path_ignores_ids_of_another_column(): void
    {
        $a = Note::create(['title' => 'A', 'status' => 'open', 'order_column' => 1]);
        $elsewhere = Note::create(['title' => 'Elsewhere', 'status' => 'shut', 'order_column' => 5]);

        Livewire::test(NoteBoard::class)
            ->call('sortChanged', $a->id, 'open', [$elsewhere->id, $a->id]);

        $this->assertSame(5, $elsewhere->fresh()->order_column);
    }

    /**
     * BUG (spatie/eloquent-sortable path): the requirement "only the visible cards trade places" is not met here -
     * `setNewOrder()` numbers the visible ids from 1, so a card hidden by the search collides with a visible one.
     */
    public function test_the_set_new_order_path_keeps_hidden_cards_in_their_slots(): void
    {
        $one = Note::create(['title' => 'Alpha one', 'status' => 'open', 'order_column' => 1]);
        $hidden = Note::create(['title' => 'Hidden', 'status' => 'open', 'order_column' => 2]);
        $two = Note::create(['title' => 'Alpha two', 'status' => 'open', 'order_column' => 3]);

        Livewire::test(NoteBoard::class)
            ->set('search', 'Alpha')
            ->call('sortChanged', $two->id, 'open', [$two->id, $one->id]);

        $positions = [$one->fresh()->order_column, $hidden->fresh()->order_column, $two->fresh()->order_column];
        $this->assertCount(3, array_unique($positions), 'Two cards share a slot: '.json_encode($positions));
    }

    // ---------------------------------------------------------------- order

    /**
     * BUG: on a move into another column the card keeps the position number it had in its OLD column. When that
     * number is lower than a hidden card's, the hidden card is pushed below a visible one (CRM's moveAndReorder gives
     * the card nextPosition() first; the port dropped that step).
     */
    public function test_a_move_does_not_push_a_hidden_card_below_a_visible_one(): void
    {
        $moved = Task::create(['title' => 'Alpha moved', 'status' => 'todo', 'position' => 1]);
        $hidden = Task::create(['title' => 'Hidden', 'status' => 'doing', 'position' => 1]);
        $visible = Task::create(['title' => 'Alpha visible', 'status' => 'doing', 'position' => 2]);

        Livewire::test(TaskBoard::class)
            ->set('search', 'Alpha')
            // Dropped at the bottom, below "Alpha visible".
            ->call('statusChanged', $moved->id, 'doing', [], [$visible->id, $moved->id]);

        $this->assertSame(TaskStatus::Doing, $moved->fresh()->status);
        $this->assertSame(
            [1, 2, 3],
            [$hidden->fresh()->position, $visible->fresh()->position, $moved->fresh()->position],
            'The hidden card was first in the column and must stay first.',
        );
    }

    public function test_a_column_with_null_positions_gets_numbered(): void
    {
        $a = Task::create(['title' => 'A', 'status' => 'todo']);
        $b = Task::create(['title' => 'B', 'status' => 'todo']);

        Livewire::test(TaskBoard::class)->call('sortChanged', $b->id, 'todo', [$b->id, $a->id]);

        $this->assertSame([1, 2], [$b->fresh()->position, $a->fresh()->position]);
    }

    // ---------------------------------------------------------------- keys

    public function test_uuid_keys_move_reorder_and_go_through_the_transition_modal(): void
    {
        $a = UuidCard::create(['title' => 'A', 'status' => 'a', 'sort' => 1]);
        $b = UuidCard::create(['title' => 'B', 'status' => 'a', 'sort' => 2]);
        $c = UuidCard::create(['title' => 'C', 'status' => 'b', 'sort' => 1]);

        $this->assertSame(36, strlen((string) $a->id));

        Livewire::test(UuidCardBoard::class)
            ->assertSeeHtml('data-kanban-record="'.$a->id.'"')
            ->call('sortChanged', (string) $b->id, 'a', [(string) $b->id, (string) $a->id])
            ->call('statusChanged', (string) $a->id, 'b', [(string) $b->id], [(string) $c->id, (string) $a->id])
            ->assertActionMounted('kanbanTransition')
            ->setActionData(['title' => 'Renamed'])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $this->assertSame(1, $b->fresh()->sort);
        $this->assertSame('b', $a->fresh()->status);
        $this->assertSame('Renamed', $a->fresh()->title);
        $this->assertSame([1, 2], [$c->fresh()->sort, $a->fresh()->sort]);
    }

    public function test_uuid_keys_open_the_edit_modal(): void
    {
        $card = UuidCard::create(['title' => 'Before', 'status' => 'a']);

        Livewire::test(UuidCardBoard::class)
            ->mountAction('kanbanEdit', ['record' => (string) $card->id])
            ->assertSchemaStateSet(['title' => 'Before'])
            ->setActionData(['title' => 'After'])
            ->callMountedAction();

        $this->assertSame('After', $card->fresh()->title);
    }

    // ---------------------------------------------------------------- column sources

    public function test_an_int_backed_enum_without_the_trait_works_end_to_end(): void
    {
        $card = PriorityCard::create(['title' => 'Card one', 'priority' => 1, 'position' => 1]);

        Livewire::test(PriorityBoard::class)
            ->assertSee('Low priority')
            ->assertSee('High priority')
            ->assertSeeHtml('data-status-id="1"')
            ->assertSeeInOrder(['Low priority', 'Card one', 'High priority'])
            ->call('statusChanged', $card->id, '2', [], [$card->id]);

        $this->assertSame(Priority::High, $card->fresh()->priority);
    }

    public function test_a_pure_enum_writes_the_case_name(): void
    {
        $card = PriorityCard::create(['title' => 'Phase card', 'phase' => Phase::Draft->name]);

        Livewire::test(PhaseBoard::class)
            ->assertSeeHtml('data-status-id="Draft"')
            ->assertSeeInOrder(['Draft', 'Phase card', 'Live'])
            ->call('statusChanged', $card->id, 'Live', [], [$card->id]);

        $this->assertSame('Live', $card->fresh()->phase);
    }

    public function test_kanban_cases_keeps_a_case_off_the_board_and_off_the_move_target_list(): void
    {
        $board = new class extends TaskBoard
        {
            protected function statuses(): Collection
            {
                return parent::statuses()->reject(fn (array $s): bool => $s['id'] === 'doing')->values();
            }
        };

        $task = Task::create(['title' => 'A', 'status' => 'todo']);

        Livewire::test($board::class)
            ->assertDontSeeHtml('data-status-id="doing"')
            ->call('statusChanged', $task->id, 'doing', [], [$task->id]);

        $this->assertSame(TaskStatus::Todo, $task->fresh()->status);
    }

    /**
     * BUG (mokhosh compatibility): mokhosh's board reads `$statusEnum::statuses()`, so an enum that customised its
     * static statuses() shaped the columns. Here the board calls StatusSource::fromEnum() directly and ignores it.
     */
    public function test_an_enum_that_overrides_its_static_statuses_is_honoured(): void
    {
        $board = new class extends TaskBoard
        {
            protected static string $statusEnum = CustomStatusesEnum::class;

            protected array $collapsedStatuses = [];
        };

        Livewire::test($board::class)
            ->assertSee('Custom backlog')
            ->assertSee('Custom finished')
            ->assertDontSeeHtml('data-status-id="doing"');
    }

    /**
     * BUG: with Filament's `->strictAuthorization()` a model without a policy must be refused (Filament itself throws
     * for resources); canMove() / canEditRecord() treat "no policy" as "allowed" regardless of the panel setting.
     */
    public function test_strict_authorization_panel_refuses_a_model_without_a_policy(): void
    {
        Filament::getCurrentOrDefaultPanel()->strictAuthorization();

        $task = Task::create(['title' => 'A', 'status' => 'todo']);

        try {
            Livewire::test(TaskBoard::class)->call('statusChanged', $task->id, 'doing', [], [$task->id]);
        } catch (Throwable) {
            // Throwing like Filament does is an acceptable fix too.
        } finally {
            Filament::getCurrentOrDefaultPanel()->strictAuthorization(false);
        }

        $this->assertSame(TaskStatus::Todo, $task->fresh()->status);
    }

    // ---------------------------------------------------------------- rendering / XSS

    public function test_user_content_is_escaped_everywhere_it_is_rendered(): void
    {
        $payload = '<img src=x onerror=alert(1)>"\'';

        $stage = Stage::create(['name' => $payload]);
        Deal::create(['title' => $payload, 'stage_id' => $stage->id]);
        Task::create(['title' => $payload, 'status' => 'todo']);

        Livewire::test(DealBoard::class)->assertDontSeeHtml('<img src=x');

        $board = new class extends TaskBoard
        {
            protected function recordDescription(Model $record): ?string
            {
                return '<script>alert(2)</script>';
            }

            protected function recordBadges(Model $record, KanbanColumn $column): array
            {
                return [['label' => '<b>badge</b>', 'tooltip' => '"><svg onload=alert(3)>']];
            }
        };

        Livewire::test($board::class)
            ->assertDontSeeHtml('<img src=x')
            ->assertDontSeeHtml('<script>alert(2)')
            ->assertDontSeeHtml('<b>badge</b>')
            ->assertDontSeeHtml('<svg onload');
    }

    public function test_a_column_id_with_quotes_does_not_break_out_of_its_attributes(): void
    {
        $board = new class extends TaskBoard
        {
            protected function statuses(): Collection
            {
                return collect([['id' => 'x" onmouseover="alert(1)', 'title' => 'Odd']]);
            }
        };

        Livewire::test($board::class)
            ->assertSee('Odd')
            ->assertDontSeeHtml('" onmouseover="alert(1)');
    }

    // ---------------------------------------------------------------- performance

    public function test_rendering_does_not_query_per_card(): void
    {
        Gate::policy(Ticket::class, TicketPolicy::class);

        $count = function (int $cards): int {
            Ticket::query()->delete();

            for ($i = 0; $i < $cards; $i++) {
                Ticket::create(['title' => 'T'.$i, 'status' => ['new', 'open', 'closed'][$i % 3], 'sort' => $i]);
            }

            DB::flushQueryLog();
            DB::enableQueryLog();

            Livewire::test(TicketBoard::class)->assertOk();

            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $few = $count(3);
        $many = $count(30);

        $this->assertSame($few, $many, "Rendering 3 cards ran {$few} queries, 30 cards ran {$many}.");
    }

    // ---------------------------------------------------------------- command

    public function test_make_kanban_in_a_subdirectory(): void
    {
        $path = app_path('Filament/Pages/Boards/ProjectsBoard.php');
        File::delete($path);

        try {
            $this->artisan('make:kanban', ['name' => 'Boards/ProjectsBoard'])->assertSuccessful();

            $code = File::get($path);

            $this->assertStringContainsString('namespace App\Filament\Pages\Boards;', $code);
            $this->assertStringContainsString('use App\Models\Project;', $code);
            $this->assertStringContainsString('use App\Enums\ProjectStatus;', $code);
            $this->assertStringContainsString("return 'Projects';", $code);
            $this->assertNotEmpty(token_get_all($code, TOKEN_PARSE));
        } finally {
            File::deleteDirectory(app_path('Filament/Pages/Boards'));
        }
    }

    public function test_make_kanban_refuses_to_overwrite_without_force(): void
    {
        $path = app_path('Filament/Pages/KeepMeBoard.php');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, '<?php // mine');

        try {
            $this->artisan('make:kanban', ['name' => 'KeepMeBoard']);
            $this->assertSame('<?php // mine', File::get($path));

            $this->artisan('make:kanban', ['name' => 'KeepMeBoard', '--force' => true])->assertSuccessful();
            $this->assertStringContainsString('class KeepMeBoard extends KanbanBoard', File::get($path));
        } finally {
            File::delete($path);
        }
    }
}
