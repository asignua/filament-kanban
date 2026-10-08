@php($extras = $this->renderKanbanSlot($this->toolbarExtras()))

@if ($this->isSearchable() || $extras !== '')
    <div class="fi-kanban-toolbar">
        @if ($this->isSearchable())
            <div class="fi-kanban-search">
                <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                    <x-filament::input
                        type="search"
                        wire:model.live.debounce.400ms="search"
                        :placeholder="__('filament-kanban::filament-kanban.search_placeholder')"
                        :aria-label="__('filament-kanban::filament-kanban.search_placeholder')"
                    />
                </x-filament::input.wrapper>
            </div>
        @endif

        {!! $extras !!}
    </div>
@endif
