<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Tests\Feature;

use Asignua\FilamentKanban\Concerns\IsKanbanStatus;
use Asignua\FilamentKanban\Support\KanbanColumn;
use Asignua\FilamentKanban\Support\StatusSource;
use Asignua\FilamentKanban\Tests\TestCase;
use Filament\Support\Contracts\HasLabel;
use LogicException;
use Workbench\App\Enums\TaskStatus;

enum PlainStatus: string
{
    case One = 'one';
    case Two = 'two';
}

enum LabelOnlyStatus: string implements HasLabel
{
    case Open = 'open';

    public function getLabel(): string
    {
        return 'Opened';
    }
}

enum SubsetStatus: string
{
    use IsKanbanStatus;

    case A = 'a';
    case B = 'b';
    case C = 'c';

    public static function kanbanCases(): array
    {
        return [self::A, self::C];
    }

    public function getTitle(): string
    {
        return 'Custom '.$this->value;
    }
}

enum PureStatus
{
    case Alpha;
}

class StatusSourceTest extends TestCase
{
    public function test_a_trait_enum_with_filament_contracts(): void
    {
        $statuses = TaskStatus::statuses();

        $this->assertSame(['todo', 'doing', 'done'], $statuses->pluck('id')->all());
        $this->assertSame('In progress', $statuses[1]['title']);
        $this->assertSame('warning', $statuses[1]['color']);
        $this->assertSame('heroicon-m-bolt', $statuses[1]['icon']);
    }

    public function test_a_plain_enum_without_the_trait_uses_the_values(): void
    {
        $statuses = StatusSource::fromEnum(PlainStatus::class);

        $this->assertSame(['one', 'two'], $statuses->pluck('title')->all());
        $this->assertNull($statuses[0]['color']);
    }

    public function test_has_label_alone_is_honoured(): void
    {
        $this->assertSame('Opened', StatusSource::fromEnum(LabelOnlyStatus::class)[0]['title']);
    }

    public function test_kanban_cases_and_get_title_can_be_overridden(): void
    {
        $this->assertSame(['a', 'c'], SubsetStatus::statuses()->pluck('id')->all());
        $this->assertSame('Custom a', SubsetStatus::statuses()[0]['title']);
    }

    public function test_a_pure_enum_uses_the_case_name(): void
    {
        $this->assertSame('Alpha', StatusSource::fromEnum(PureStatus::class)[0]['id']);
    }

    public function test_a_column_reads_like_a_mokhosh_status_array(): void
    {
        $column = KanbanColumn::fromArray(['id' => 3, 'title' => 'Three', 'value' => 3, 'color' => 'danger']);

        $this->assertSame('3', $column['id']);
        $this->assertSame('Three', $column['title']);
        $this->assertSame(3, $column->value);
        $this->assertSame('danger', $column->colorName());
        $this->assertTrue(isset($column['records']));
    }

    public function test_a_column_without_an_id_is_rejected(): void
    {
        $this->expectException(LogicException::class);

        KanbanColumn::fromArray(['title' => 'No id']);
    }

    public function test_a_column_is_read_only(): void
    {
        $this->expectException(LogicException::class);

        $column = KanbanColumn::fromArray(['id' => 'x']);
        $column['title'] = 'changed';
    }

    public function test_an_array_colour_has_no_class_name(): void
    {
        $this->assertNull(KanbanColumn::fromArray(['id' => 'x', 'color' => [500 => '#fff']])->colorName());
    }
}
