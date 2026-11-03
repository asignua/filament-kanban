{{--
    Laravel Echo for the boards when `realtime.echo` = 'plugin': Filament already ships Echo + pusher-js
    (window.EchoFactory, a core script), so nothing is bundled here. Runs before Livewire starts, so the board finds
    window.Echo when it subscribes. An Echo created by the app or by Filament's own config is left alone.
--}}
@use('Asignua\FilamentKanban\Support\Realtime')
@use('Illuminate\Support\Facades\Route')
@if (auth()->check() && Realtime::enabled() && Realtime::echoSource() === Realtime::ECHO_PLUGIN)
    <script data-navigate-once>
        (() => {
            if (window.Echo || ! window.EchoFactory) {
                return;
            }

            {{-- Semicolons everywhere: a Blade directive (@js) swallows the line break after it. --}}
            const options = @js(Realtime::echoOptions());
            const scheme = options.scheme || window.location.protocol.replace(':', '');
            const tls = scheme === 'https';
            const port = options.port || (tls ? 443 : 80);
            const config = {
                broadcaster: options.broadcaster,
                key: options.key,
                forceTLS: tls,
                enabledTransports: ['ws', 'wss'],
                disableStats: true,
                authEndpoint: @js(Route::has('broadcasting.auth') ? route('broadcasting.auth') : url('/broadcasting/auth')),
            };

            if (options.broadcaster === 'pusher' && options.cluster && ! options.host) {
                config.cluster = options.cluster;
            } else {
                Object.assign(config, { wsHost: options.host || window.location.hostname, wsPort: port, wssPort: port });
            }

            window.Echo = new window.EchoFactory(config);
            window.dispatchEvent(new CustomEvent('EchoLoaded'));
        })();
    </script>
@endif
