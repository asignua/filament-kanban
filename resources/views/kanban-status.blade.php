@php($accent = $column->colorName())

<section
    wire:key="kanban-column-{{ $column->id }}"
    role="listitem"
    data-column="{{ $column->id }}"
    @class(['fi-kanban-column', 'fi-color-'.$accent => $accent !== null])
    x-bind:class="{ 'fi-kanban-column--collapsed': isCollapsed(@js($column->id), @js($column->collapsed)) }"
>
    @include(static::$headerView)

    <div class="fi-kanban-cards" data-status-id="{{ $column->id }}" x-init="sortable($el)">
        @foreach ($column->records as $record)
            @include(static::$recordView, ['record' => $record, 'column' => $column])
        @endforeach

        <p class="fi-kanban-drop-hint">{{ __('filament-kanban::filament-kanban.drop_here') }}</p>
    </div>
</section>
