<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Real-time board updates (opt-in)
    |--------------------------------------------------------------------------
    |
    | enabled — true: a change on a board (a card moved, reordered, edited) is announced over a
    |           websocket (Laravel Reverb or any Pusher-compatible server) and every other open copy
    |           of the board refreshes itself. false: nothing is broadcast, nothing is listened to,
    |           no extra polling. null: on when the default broadcaster is reverb / pusher / ably.
    |
    | connection — the broadcasting connection to publish on; null = default.
    |
    | echo — who puts Laravel Echo into the browser:
    |   'plugin'   — the plugin does (Filament's bundled Echo), configured from the connection
    |                + `client` below. Nothing else to set up.
    |   'filament' — Filament's own `filament.broadcasting.echo` config.
    |   'host'     — your app already loads window.Echo (your Vite bundle).
    |
    | client — where the BROWSER connects, when it differs from where PHP publishes (e.g. PHP ->
    |          reverb:8080 inside Docker, the browser -> your public host behind nginx). Empty =
    |          the page's own host, protocol and default port.
    |
    | register_auth_route — register /broadcasting/auth (web + auth middleware) if your app has not.
    |
    | debounce — milliseconds a burst of changes is collected for before the board refreshes once.
    |
    */

    'realtime' => [
        'enabled' => env('FILAMENT_KANBAN_REALTIME'),
        'connection' => env('FILAMENT_KANBAN_BROADCAST_CONNECTION'),
        'echo' => env('FILAMENT_KANBAN_ECHO', 'plugin'),
        'client' => [
            'host' => env('FILAMENT_KANBAN_WS_HOST', env('VITE_REVERB_HOST')),
            'port' => env('FILAMENT_KANBAN_WS_PORT', env('VITE_REVERB_PORT')),
            'scheme' => env('FILAMENT_KANBAN_WS_SCHEME', env('VITE_REVERB_SCHEME')),
        ],
        'register_auth_route' => true,
        'debounce' => 500,
        // Seconds between refreshes while the browser's websocket has given up for good (pusher-js `failed` after a
        // Reverb restart). 0 = off.
        'fallback_poll' => env('FILAMENT_KANBAN_FALLBACK_POLL', 60),
    ],

];
