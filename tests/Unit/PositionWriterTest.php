<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Tests\Unit;

use Asignua\FilamentKanban\Support\PositionWriter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PositionWriterTest extends TestCase
{
    /**
     * @return iterable<string, array{list<int|string>, array<int, mixed>, list<string>}>
     */
    public static function cases(): iterable
    {
        yield 'everything visible, a swap' => [[1, 2, 3], [3, 1, 2], ['3', '1', '2']];
        yield 'hidden cards keep their slots' => [[1, 2, 3, 4, 5], [5, 1], ['5', '2', '3', '4', '1']];
        yield 'a visible card moved to the end of a longer column' => [['a', 'b', 'c', 'd'], ['d', 'a'], ['d', 'b', 'c', 'a']];
        yield 'foreign ids are ignored' => [[1, 2, 3], [99, 3, 1], ['3', '2', '1']];
        yield 'duplicates are ignored' => [[1, 2, 3], [2, 2, 1], ['2', '1', '3']];
        yield 'non scalar junk is ignored' => [[1, 2], [['x'], null, 2, 1], ['2', '1']];
        yield 'nothing visible, nothing changes' => [[1, 2, 3], [], ['1', '2', '3']];
        yield 'ulids' => [['01A', '01B', '01C'], ['01C', '01A'], ['01C', '01B', '01A']];
    }

    /**
     * @param list<int|string>  $column
     * @param array<int, mixed> $visible
     * @param list<string>      $expected
     */
    #[DataProvider('cases')]
    public function test_slots(array $column, array $visible, array $expected): void
    {
        $this->assertSame($expected, PositionWriter::slots($column, $visible));
    }
}
