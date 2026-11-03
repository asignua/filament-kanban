<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Tests\Unit;

use Asignua\FilamentKanban\Support\Realtime;
use Asignua\FilamentKanban\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Workbench\App\Filament\Pages\TaskBoard;

class RealtimeConfigTest extends TestCase
{
    /**
     * @return array<string, array{mixed, string, bool}>
     */
    public static function resolutions(): array
    {
        return [
            'auto, null broadcaster' => [null, 'null', false],
            'auto, log broadcaster' => [null, 'log', false],
            'auto, reverb' => [null, 'reverb', true],
            'auto, pusher' => [null, 'pusher', true],
            'auto, ably' => [null, 'ably', true],
            'empty string is auto' => ['', 'reverb', true],
            'forced on' => [true, 'null', true],
            'forced off' => [false, 'reverb', false],
            'env string true' => ['true', 'null', true],
            'env string false' => ['false', 'reverb', false],
            'env string 0' => ['0', 'reverb', false],
        ];
    }

    #[DataProvider('resolutions')]
    public function test_enabled_resolves_from_config_or_the_broadcaster(mixed $setting, string $default, bool $expected): void
    {
        config(['filament-kanban.realtime.enabled' => $setting, 'broadcasting.default' => $default]);

        $this->assertSame($expected, Realtime::enabled());
    }

    public function test_the_driver_of_a_named_connection_decides_in_auto_mode(): void
    {
        config([
            'filament-kanban.realtime.enabled' => null,
            'broadcasting.default' => 'live',
            'broadcasting.connections.live' => ['driver' => 'reverb'],
        ]);

        $this->assertTrue(Realtime::enabled());

        config(['filament-kanban.realtime.connection' => 'quiet', 'broadcasting.connections.quiet' => ['driver' => 'log']]);

        $this->assertFalse(Realtime::enabled());
    }

    public function test_it_is_off_by_default(): void
    {
        $this->assertFalse(Realtime::enabled());
    }

    public function test_the_channel_name_has_no_dots_or_backslashes_and_the_board_class_survives(): void
    {
        $channel = Realtime::channelFor(TaskBoard::class);

        $this->assertStringNotContainsString('\\', $channel);
        $this->assertSame('filament-kanban.board.Workbench-App-Filament-Pages-TaskBoard', $channel);
        $this->assertSame('echo-private:'.$channel.',.filament-kanban.board-changed', Realtime::listener(TaskBoard::class));
    }
}
