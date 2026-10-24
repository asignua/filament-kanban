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

    @if ($column->hiddenCount() > 0)
        {{-- Outside the sortable list on purpose: SortableJS must not see it as a slot. --}}
        <div class="fi-kanban-more">
            <button
                type="button"
                class="fi-kanban-more-button"
                wire:click="loadMoreRecords(@js($column->id))"
                wire:loading.attr="disabled"
                wire:target="loadMoreRecords(@js($column->id))"
            >
                <span>{{ __('filament-kanban::filament-kanban.show_more', ['count' => min($this->columnPageSize($column) ?? $column->hiddenCount(), $column->hiddenCount())]) }}</span>
                <span class="fi-kanban-more-hidden">{{ __('filament-kanban::filament-kanban.hidden_count', ['hidden' => $column->hiddenCount()]) }}</span>
                <x-filament::loading-indicator wire:loading wire:target="loadMoreRecords(@js($column->id))" class="fi-kanban-more-spinner" />
            </button>
        </div>
    @endif
</section>
