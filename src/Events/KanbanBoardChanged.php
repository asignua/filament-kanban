<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Events;

use Asignua\FilamentKanban\Support\Realtime;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * "Something changed on this board" - a signal, not data. It carries the board, the key of the card and the kind of
 * change, nothing else: every open copy of the board re-reads the cards itself, so policies, scopes and filters stay
 * on the server and the socket payload never leaks a card.
 *
 * Broadcast right away (no queue: without a worker a queued event would silently never arrive) and only after the
 * surrounding transaction committed, so a recipient never re-reads a state that is not there yet.
 */
final class KanbanBoardChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use InteractsWithBroadcasting;

    /** Event name for Echo (the listener is `.filament-kanban.board-changed`). */
    public const string NAME = 'filament-kanban.board-changed';

    /** Private channel prefix; the rest is Realtime::channelFor(). */
    public const string CHANNEL = 'filament-kanban.board.';

    public const string MOVED = 'moved';

    public const string SORTED = 'sorted';

    public const string CREATED = 'created';

    public const string UPDATED = 'updated';

    public const string DELETED = 'deleted';

    public const array TYPES = [self::MOVED, self::SORTED, self::CREATED, self::UPDATED, self::DELETED];

    /**
     * @param class-string $board  The board (Livewire page) the change was made on.
     * @param string       $record Key of the card, as a string.
     * @param string       $type   One of self::TYPES.
     * @param string|null  $origin Livewire component id of the copy that made the change; that copy ignores the event.
     */
    public function __construct(
        public readonly string $board,
        public readonly string $record,
        public readonly string $type,
        public readonly ?string $origin = null,
    ) {
        $this->broadcastVia(Realtime::connection());
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(Realtime::channelFor($this->board))];
    }

    public function broadcastAs(): string
    {
        return self::NAME;
    }

    /**
     * @return array{board: string, record: string, type: string, origin: string|null}
     */
    public function broadcastWith(): array
    {
        return [
            'board' => $this->board,
            'record' => $this->record,
            'type' => $this->type,
            'origin' => $this->origin,
        ];
    }
}
