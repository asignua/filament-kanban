<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Fired after a card changed column on a board (not for a reorder inside one column).
 */
final readonly class KanbanRecordMoved
{
    /**
     * @param class-string         $board The Livewire page the card was dropped on.
     * @param array<string, mixed> $data  What the transition modal collected, if one was shown.
     */
    public function __construct(
        public Model $record,
        public string $from,
        public string $to,
        public string $board,
        public array $data = [],
    ) {}
}
