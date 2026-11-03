<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Concerns;

use Asignua\FilamentKanban\Support\Realtime;
use Livewire\Attributes\Renderless;

/**
 * Real-time board updates (opt-in, see README "Real-time"): announces this board's changes to every other open copy
 * and reacts to theirs. All of it is inert while `filament-kanban.realtime.enabled` resolves to false.
 *
 * The listener is registered through getListeners(): a board that declares its own getListeners() must merge
 * `kanbanRealtimeListeners()` into it.
 */
trait SyncsKanbanBoard
{
    /**
     * @return array<string, string>
     */
    protected function getListeners(): array
    {
        return $this->kanbanRealtimeListeners();
    }

    /**
     * @return array<string, string>
     */
    protected function kanbanRealtimeListeners(): array
    {
        return Realtime::enabled() ? [Realtime::listener(static::class) => 'onKanbanBoardChanged'] : [];
    }

    /**
     * Another copy of the board changed something. Nothing is re-read here: the browser collects a burst into one
     * refresh, and holds it back (with a "Board changed" notice) while a drag or a modal is in progress.
     *
     * @param array<string, mixed> $payload
     */
    #[Renderless]
    public function onKanbanBoardChanged(array $payload = []): void
    {
        if (!Realtime::enabled()) {
            return;
        }

        // The change this very copy made: its own response already shows it.
        if (isset($payload['origin']) && $payload['origin'] === $this->getId()) {
            return;
        }

        $this->dispatch('kanban-board-changed', busy: $this->kanbanHasOpenModal());
    }

    /**
     * Announce a change made by code that is not the plugin's own (a create or delete action of your board, a custom
     * write hook). The plugin's moves, reorders and edits already do it.
     */
    protected function broadcastKanbanChange(int|string $record, string $type): void
    {
        Realtime::changed(static::class, $record, $type, $this->getId());
    }

    private function kanbanHasOpenModal(): bool
    {
        return $this->mountedActions !== [];
    }
}
