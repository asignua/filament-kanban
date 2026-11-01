<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Tests\Feature;

use Asignua\FilamentKanban\Tests\Fixtures\LazyTaskBoard;
use Asignua\FilamentKanban\Tests\TestCase;
use Livewire\Livewire;
use Workbench\App\Filament\Pages\TaskBoard;
use Workbench\App\Models\Task;

/**
 * Guards the weight of the board: cards live only in the HTML (never in the Livewire snapshot), and a plain card
 * does not carry Livewire morph markers for conditional parts it does not use.
 */
class PageWeightTest extends TestCase
{
    private function seedCards(int $count): void
    {
        $statuses = ['todo', 'doing', 'done'];

        for ($i = 1; $i <= $count; $i++) {
            Task::create(['title' => 'Card number '.$i, 'status' => $statuses[$i % 3], 'position' => intdiv($i, 3)]);
        }
    }

    public function test_the_snapshot_does_not_grow_with_the_number_of_cards(): void
    {
        $this->seedCards(3);
        $small = strlen(json_encode(Livewire::test(TaskBoard::class)->snapshot, JSON_THROW_ON_ERROR));

        $this->seedCards(300);
        $large = strlen(json_encode(Livewire::test(TaskBoard::class)->snapshot, JSON_THROW_ON_ERROR));

        $this->assertSame($small, $large);
    }

    public function test_a_plain_card_stays_light(): void
    {
        $this->seedCards(120);

        $html = Livewire::test(TaskBoard::class)->html();

        $this->assertSame(120, substr_count($html, 'data-kanban-record='));
        // About 330 bytes per card; the fixed part (three column headers, toolbar) is under 20 KB.
        $this->assertLessThan(120 * 450 + 20_000, strlen($html));
    }

    public function test_a_lazy_board_response_does_not_depend_on_the_total_number_of_cards(): void
    {
        $this->seedCards(30);
        $few = strlen(Livewire::test(LazyTaskBoard::class)->html());

        $this->seedCards(600);
        $many = strlen(Livewire::test(LazyTaskBoard::class)->html());

        // Only the "N hidden" figures differ.
        $this->assertLessThan(200, abs($many - $few));
    }
}
