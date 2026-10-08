@php
    $recordKey = $record->getKey();
    $url = $this->recordUrl($record);
    $draggable = $this->isRecordDraggable($record);
    $editable = $url === null && ! $this->disableEditModal && $this->canEditRecord($record);
    $description = $this->recordDescription($record);
    $badges = $this->recordBadges($record, $column);
    $extras = $this->renderKanbanSlot($this->recordExtras($record, $column));
@endphp

<article
    wire:key="kanban-record-{{ $recordKey }}"
    data-kanban-record="{{ $recordKey }}"
    @if (! $draggable) data-locked="true" @endif
    @class([
        'fi-kanban-card',
        'fi-kanban-card--flash' => $this->isRecentlyUpdated($record),
        'fi-kanban-card--locked' => ! $draggable,
        'fi-kanban-card--clickable' => $url !== null || $editable,
        $this->recordClasses($record, $column),
    ])
>
    @if ($url !== null)
        <a href="{{ $url }}" class="fi-kanban-card-title fi-kanban-card-link">{{ $this->recordTitle($record) }}</a>
    @elseif ($editable)
        <button type="button" class="fi-kanban-card-title fi-kanban-card-link" x-on:click="open(@js((string) $recordKey))">{{ $this->recordTitle($record) }}</button>
    @else
        <span class="fi-kanban-card-title">{{ $this->recordTitle($record) }}</span>
    @endif

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

    @unless ($draggable)
        <span class="sr-only">{{ __('filament-kanban::filament-kanban.card_locked') }}</span>
    @endunless
</article>
