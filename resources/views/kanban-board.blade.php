@php
    use Filament\Support\Facades\FilamentAsset;
    use Asignua\FilamentKanban\KanbanServiceProvider;
    use Asignua\FilamentKanban\Support\Realtime;

    $realtime = Realtime::enabled();
@endphp

<x-filament-panels::page>
    <link rel="stylesheet" href="{{ FilamentAsset::getStyleHref(KanbanServiceProvider::STYLESHEET, KanbanServiceProvider::PACKAGE) }}" />

    <div
        class="fi-kanban"
        x-load
        x-load-src="{{ FilamentAsset::getAlpineComponentSrc(KanbanServiceProvider::COMPONENT, KanbanServiceProvider::PACKAGE) }}"
        x-data="kanbanBoard({
            storageKey: @js('filament-kanban.'.static::class),
            group: @js('filament-kanban-'.md5(static::class)),@if ($realtime)
            realtime: @js(Realtime::boardOptions()),@endif
        })"
    >
        @include('filament-kanban::partials.realtime-notice')

        @include(static::$toolbarView)

        <div class="fi-kanban-board" role="list" aria-label="{{ $this->getTitle() }}">
            @foreach ($columns as $column)
                @include(static::$statusView, ['column' => $column, 'status' => $column])
            @endforeach
        </div>

        @include(static::$scriptsView)
    </div>
</x-filament-panels::page>
