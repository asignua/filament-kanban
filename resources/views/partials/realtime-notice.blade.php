{{--
    Real time (opt-in): the "Board changed" notice shown instead of a refresh while a drag or a modal is in progress.
    Shared by the free and the Pro board views; renders nothing when real time is off.
--}}
@if (\Asignua\FilamentKanban\Support\Realtime::enabled())
    <div class="fi-kanban-notice" role="status" x-cloak x-show="pending">
        <span>{{ __('filament-kanban::filament-kanban.board_changed') }}</span>
        <button type="button" class="fi-kanban-notice-button" x-on:click="refresh()">
            {{ __('filament-kanban::filament-kanban.board_refresh') }}
        </button>
    </div>
@endif
