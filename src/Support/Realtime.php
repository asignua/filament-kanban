<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Support;

use Asignua\FilamentKanban\Concerns\InteractsWithKanban;
use Asignua\FilamentKanban\Events\KanbanBoardChanged;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

/**
 * Real-time board updates: config, channel naming, the channel's authorization and the one place that broadcasts.
 * Everything here is inert while `filament-kanban.realtime.enabled` resolves to false.
 */
final class Realtime
{
    /** Broadcasters that deliver to the browser (the rest, log and null, do not). */
    public const array SOCKET_BROADCASTERS = ['reverb', 'pusher', 'ably'];

    public const string ECHO_PLUGIN = 'plugin';

    public const string ECHO_FILAMENT = 'filament';

    public const string ECHO_HOST = 'host';

    /** @var (Closure(Authenticatable, class-string): bool)|null */
    private static ?Closure $authorizer = null;

    public static function enabled(): bool
    {
        $enabled = config('filament-kanban.realtime.enabled');

        if (is_bool($enabled)) {
            return $enabled;
        }

        // env() gives strings: "true" / "false" / "".
        if (is_string($enabled) && $enabled !== '') {
            return filter_var($enabled, FILTER_VALIDATE_BOOLEAN);
        }

        return in_array(self::broadcaster(), self::SOCKET_BROADCASTERS, true);
    }

    public static function debounce(): int
    {
        return max(0, (int) config('filament-kanban.realtime.debounce', 500));
    }

    /**
     * Seconds between refreshes while the websocket is dead for good (pusher-js `failed`); 0 turns the fallback off.
     */
    public static function fallbackPoll(): int
    {
        return max(0, (int) config('filament-kanban.realtime.fallback_poll', 60));
    }

    /**
     * The `realtime` option of the board's Alpine component.
     *
     * @return array{debounce: int, fallback_poll: int}
     */
    public static function boardOptions(): array
    {
        return ['debounce' => self::debounce(), 'fallback_poll' => self::fallbackPoll()];
    }

    public static function connection(): ?string
    {
        return self::nonEmptyString(config('filament-kanban.realtime.connection'));
    }

    /**
     * The driver behind the connection (reverb, pusher, ably, log, null...).
     */
    public static function broadcaster(): ?string
    {
        $connection = self::connection() ?? config('broadcasting.default');

        if (!is_string($connection)) {
            return null;
        }

        return self::nonEmptyString(config('broadcasting.connections.'.$connection.'.driver')) ?? $connection;
    }

    public static function echoSource(): string
    {
        $source = config('filament-kanban.realtime.echo', self::ECHO_PLUGIN);

        return in_array($source, [self::ECHO_PLUGIN, self::ECHO_FILAMENT, self::ECHO_HOST], true) ? $source : self::ECHO_PLUGIN;
    }

    public static function registerAuthRoute(): bool
    {
        return (bool) config('filament-kanban.realtime.register_auth_route', true);
    }

    /**
     * Echo options for the browser when the plugin brings Echo itself.
     *
     * @return array{broadcaster: string, key: string, cluster: string|null, host: string|null, port: int|null, scheme: string|null}
     */
    public static function echoOptions(): array
    {
        $connection = self::connection() ?? (string) config('broadcasting.default');
        $settings = config('broadcasting.connections.'.$connection);
        $settings = is_array($settings) ? $settings : [];
        $options = is_array($settings['options'] ?? null) ? $settings['options'] : [];
        $client = self::clientAddress();

        return [
            'broadcaster' => (self::broadcaster() ?? 'reverb') === 'reverb' ? 'reverb' : 'pusher',
            'key' => (string) ($settings['key'] ?? ''),
            'cluster' => self::nonEmptyString($options['cluster'] ?? null),
            'host' => $client['host'],
            'port' => $client['port'],
            'scheme' => $client['scheme'],
        ];
    }

    /**
     * Where the BROWSER reaches the websocket server. Per field, the first that is set wins:
     * the plugin's own `realtime.client.*` (env FILAMENT_KANBAN_WS_*; the config falls back to Laravel's
     * `VITE_REVERB_HOST/PORT/SCHEME`), then Filament's own Echo config (`filament.broadcasting.echo`:
     * wsHost, wsPort/wssPort, forceTLS), else null (the page's own host, protocol and default port).
     *
     * @return array{host: string|null, port: int|null, scheme: string|null}
     */
    public static function clientAddress(): array
    {
        $client = config('filament-kanban.realtime.client');
        $client = is_array($client) ? $client : [];
        $echo = config('filament.broadcasting.echo');
        $echo = is_array($echo) ? $echo : [];

        $host = self::nonEmptyString($client['host'] ?? null) ?? self::nonEmptyString($echo['wsHost'] ?? null);

        $scheme = self::nonEmptyString($client['scheme'] ?? null);

        if ($scheme === null && isset($echo['forceTLS'])) {
            $scheme = filter_var($echo['forceTLS'], FILTER_VALIDATE_BOOLEAN) ? 'https' : 'http';
        }

        $port = $client['port'] ?? null;

        if (!is_numeric($port)) {
            $own = $scheme === 'https' ? [$echo['wssPort'] ?? null, $echo['wsPort'] ?? null] : [$echo['wsPort'] ?? null, $echo['wssPort'] ?? null];
            $port = null;

            foreach ($own as $candidate) {
                if (is_numeric($candidate)) {
                    $port = $candidate;

                    break;
                }
            }
        }

        return ['host' => $host, 'port' => is_numeric($port) ? (int) $port : null, 'scheme' => $scheme];
    }

    /**
     * Private channel of one board. The board class is the channel key, with `\` as `-` (a channel parameter cannot
     * hold a dot or a backslash, and PHP class names never contain a hyphen, so it maps back unambiguously).
     */
    public static function channelFor(string $board): string
    {
        return KanbanBoardChanged::CHANNEL.str_replace('\\', '-', ltrim($board, '\\'));
    }

    /**
     * Livewire listener of a board's channel: `echo-private:<channel>,.<event>`.
     */
    public static function listener(string $board): string
    {
        return 'echo-private:'.self::channelFor($board).',.'.KanbanBoardChanged::NAME;
    }

    /**
     * Announces a change to everyone watching the board. A no-op while real-time is off.
     *
     * @param class-string $board
     * @param string       $type   one of KanbanBoardChanged::TYPES
     * @param string|null  $origin id of the Livewire component that caused it (that copy does not refresh itself)
     */
    public static function changed(string $board, int|string $record, string $type, ?string $origin = null): void
    {
        if (!self::enabled()) {
            return;
        }

        event(new KanbanBoardChanged($board, (string) $record, $type, $origin));
    }

    /**
     * Extra rule for the channel, on top of the board's own `canAccess()`. Return false to keep a user off the
     * channel (for example a board scoped to a project the user does not belong to).
     *
     * @param (Closure(Authenticatable, class-string): bool)|null $callback
     */
    public static function authorizeUsing(?Closure $callback): void
    {
        self::$authorizer = $callback;
    }

    /**
     * May this user listen to this board's channel? The same check that lets them open the board (`canAccess()`),
     * then the optional authorizeUsing() rule. The key from the browser is never trusted: it must name a class that
     * really is a Kanban board.
     */
    public static function authorizeChannel(?Authenticatable $user, string $key, ?string $guard = null): bool
    {
        if ($user === null || preg_match('/^[A-Za-z0-9_-]+$/', $key) !== 1) {
            return false;
        }

        $board = self::boardFromKey($key);

        if ($board === null) {
            return false;
        }

        if ($guard !== null) {
            Auth::shouldUse($guard);
        }

        if (!$board::canAccess()) {
            return false;
        }

        return self::$authorizer === null || (bool) (self::$authorizer)($user, $board);
    }

    /**
     * @return (class-string<\Filament\Pages\Page>|class-string<\Filament\Resources\Pages\Page>)|null
     */
    private static function boardFromKey(string $key): ?string
    {
        $class = str_replace('-', '\\', $key);

        if (!class_exists($class) || !in_array(InteractsWithKanban::class, class_uses_recursive($class), true) || !method_exists($class, 'canAccess')) {
            return null;
        }

        /** @var class-string<\Filament\Pages\Page>|class-string<\Filament\Resources\Pages\Page> $class */
        return $class;
    }

    private static function nonEmptyString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
