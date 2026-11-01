@use('Illuminate\Support\Js')

@php
    $recordKey = $record->getKey();
    $url = $this->recordUrl($record);
    $draggable = $this->isRecordDraggable($record);
    $editable = $url === null && ! $this->disableEditModal && $this->canEditRecord($record);
    $description = $this->recordDescription($record);
    $badges = $this->recordBadges($record, $column);
    $extras = $this->renderKanbanSlot($this->recordExtras($record, $column));

    [$titleTag, $titleAttributes] = match (true) {
        $url !== null => ['a', 'href="'.e($url).'" class="fi-kanban-card-title fi-kanban-card-link"'],
        $editable => ['button', 'type="button" class="fi-kanban-card-title fi-kanban-card-link" x-on:click="open('.Js::from((string) $recordKey)->toHtml().')"'],
        default => ['span', 'class="fi-kanban-card-title"'],
    };
@endphp

<article
    wire:key="kanban-record-{{ $recordKey }}"
    data-kanban-record="{{ $recordKey }}"
    {!! $draggable ? '' : 'data-locked="true"' !!}
    @class([
        'fi-kanban-card',
        'fi-kanban-card--flash' => $this->isRecentlyUpdated($record),
        'fi-kanban-card--locked' => ! $draggable,
        'fi-kanban-card--clickable' => $url !== null || $editable,
        $this->recordClasses($record, $column),
    ])
>
    {{-- One dynamic tag instead of an @if chain, and the lock hint as an echo: every @if/@foreach costs a pair of Livewire morph markers on EVERY card. --}}
    <{{ $titleTag }} {!! $titleAttributes !!}>{{ $this->recordTitle($record) }}</{{ $titleTag }}>

    @if (($description !== null && $description !== '') || $badges !== [] || $extras !== '')
        @if ($description !== null && $description !== '')
            <p class="fi-kanban-card-description">{{ $description }}</p>
        @endif

        @if ($badges !== [])
            <div class="fi-kanban-card-badges">
                @foreach ($badges as $badge)
                    <x-filament::badge size="sm" :color="$badge['color'] ?? 'gray'" :icon="$badge['icon'] ?? null" :title="$badge['tooltip'] ?? null">{{ $badge['label'] }}</x-filament::badge>
                @endforeach
            </div>
        @endif

        {!! $extras !!}
    @endif

    {!! $draggable ? '' : '<span class="sr-only">'.e(__('filament-kanban::filament-kanban.card_locked')).'</span>' !!}
</article>
