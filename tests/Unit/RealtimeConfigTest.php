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

    public function test_client_address_resolves_plugin_then_vite_reverb_then_filament_then_page(): void
    {
        $key = 'filament-kanban.realtime.client';
        $none = ['host' => null, 'port' => null, 'scheme' => null];

        // 4. nothing set: the page decides (nulls).
        config([$key => $none, 'filament.broadcasting.echo' => null]);
        $this->assertSame($none, Realtime::clientAddress());

        // 3. Filament's own Echo config.
        config(['filament.broadcasting.echo' => ['wsHost' => 'fil.example.com', 'wsPort' => 6001, 'wssPort' => 6443, 'forceTLS' => true]]);
        $this->assertSame(['host' => 'fil.example.com', 'port' => 6443, 'scheme' => 'https'], Realtime::clientAddress());

        config(['filament.broadcasting.echo' => ['wsHost' => 'fil.example.com', 'wsPort' => '6001', 'forceTLS' => false]]);
        $this->assertSame(['host' => 'fil.example.com', 'port' => 6001, 'scheme' => 'http'], Realtime::clientAddress());

        // 2. VITE_REVERB_* (read by the config file through env()) beats Filament.
        $_SERVER['VITE_REVERB_HOST'] = 'vite.example.com';
        $_SERVER['VITE_REVERB_PORT'] = '8443';
        $_SERVER['VITE_REVERB_SCHEME'] = 'https';

        try {
            $loaded = require __DIR__.'/../../config/filament-kanban.php';
        } finally {
            unset($_SERVER['VITE_REVERB_HOST'], $_SERVER['VITE_REVERB_PORT'], $_SERVER['VITE_REVERB_SCHEME']);
        }

        $this->assertSame(['host' => 'vite.example.com', 'port' => '8443', 'scheme' => 'https'], $loaded['realtime']['client']);
        config([$key => $loaded['realtime']['client']]);
        $this->assertSame(['host' => 'vite.example.com', 'port' => 8443, 'scheme' => 'https'], Realtime::clientAddress());

        // 1. The plugin's own value beats everything, field by field.
        config([$key => ['host' => 'own.example.com', 'port' => null, 'scheme' => null]]);
        $this->assertSame(['host' => 'own.example.com', 'port' => 6001, 'scheme' => 'http'], Realtime::clientAddress());

        // echoOptions() carries the resolved address.
        $this->assertSame('own.example.com', Realtime::echoOptions()['host']);
    }

    public function test_fallback_poll_defaults_to_sixty_seconds_and_zero_turns_it_off(): void
    {
        $this->assertSame(60, Realtime::fallbackPoll());
        $this->assertSame(['debounce' => 500, 'fallback_poll' => 60], Realtime::boardOptions());

        config(['filament-kanban.realtime.fallback_poll' => '15']);
        $this->assertSame(15, Realtime::fallbackPoll());

        config(['filament-kanban.realtime.fallback_poll' => 0]);
        $this->assertSame(0, Realtime::fallbackPoll());

        config(['filament-kanban.realtime.fallback_poll' => -5]);
        $this->assertSame(0, Realtime::fallbackPoll());
    }
}
