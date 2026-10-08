<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Concerns;

use Asignua\FilamentKanban\Events\KanbanRecordMoved;
use Asignua\FilamentKanban\Support\PositionWriter;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

/**
 * Drag and drop on the server: authorize, validate, optionally ask the transition form, then write status and order.
 *
 * `statusChanged()` / `sortChanged()` are the only entry points the browser calls (they are also the Livewire
 * listeners `status-changed` / `sort-changed` that mokhosh/filament-kanban's own scripts dispatch). The `on*`
 * hooks below them are protected on purpose: they write without asking, so a public one could be called straight
 * from the browser console. Make an override public only if you must.
 */
trait MovesKanbanRecords
{
    /**
     * A card was dropped into another column (or into the same one: then it is a reorder).
     *
     * @param array<int, mixed> $fromOrderedIds Visible card keys of the column it left, in order.
     * @param array<int, mixed> $toOrderedIds   Visible card keys of the column it entered, in order.
     */
    #[On('status-changed')]
    public function statusChanged(int|string $recordId, string $status, array $fromOrderedIds = [], array $toOrderedIds = []): void
    {
        $record = $this->findRecord($recordId);

        if ($record === null) {
            $this->kanbanNotify(__('filament-kanban::filament-kanban.record_missing'), 'warning');

            return;
        }

        $target = $this->findColumn($status);

        if ($target === null) {
            $this->kanbanNotify(__('filament-kanban::filament-kanban.column_unknown'), 'danger');

            return;
        }

        $from = $this->statusOf($record);

        if ($from === $target->id) {
            $this->sortChanged($recordId, $status, $toOrderedIds);

            return;
        }

        if (!$this->guardMove($record, $from, $target->id)) {
            return;
        }

        $schema = $this->transitionSchemaFor($record, $from, $target->id);

        if ($schema !== null && $schema !== []) {
            $this->mountAction('kanbanTransition', [
                'record' => $record->getKey(),
                'from' => $from,
                'to' => $target->id,
                'fromOrderedIds' => $fromOrderedIds,
                'toOrderedIds' => $toOrderedIds,
            ]);

            return;
        }

        $this->moveRecord($record, $from, $target->id, $fromOrderedIds, $toOrderedIds);
    }

    /**
     * A card was dropped inside its own column.
     *
     * @param array<int, mixed> $orderedIds Visible card keys of the column, in order.
     */
    #[On('sort-changed')]
    public function sortChanged(int|string $recordId, string $status, array $orderedIds = []): void
    {
        $record = $this->findRecord($recordId);

        if ($record === null) {
            $this->kanbanNotify(__('filament-kanban::filament-kanban.record_missing'), 'warning');

            return;
        }

        $current = $this->statusOf($record);

        if ($current !== $status) {
            // The card is not where the browser thinks it is: nothing to do, the re-render puts it right.
            return;
        }

        if (!$this->isRecordDraggable($record) || !$this->canMove($record, $current, $current)) {
            $this->kanbanNotify(__('filament-kanban::filament-kanban.move_denied'), 'danger');

            return;
        }

        DB::transaction(fn () => $this->onSortChanged($recordId, $status, $orderedIds));
    }

    /**
     * Default: set the status, then renumber the destination column from the visible order.
     *
     * @param array<int, mixed> $fromOrderedIds
     * @param array<int, mixed> $toOrderedIds
     */
    protected function onStatusChanged(int|string $recordId, string $status, array $fromOrderedIds, array $toOrderedIds): void
    {
        $record = $this->findRecord($recordId);
        $column = $this->findColumn($status);

        if ($record === null || $column === null) {
            return;
        }

        $record->setAttribute(static::$recordStatusAttribute, $column->value);
        $record->save();

        $this->persistOrder($status, $toOrderedIds);
    }

    /**
     * Default: renumber the column from the visible order.
     *
     * @param array<int, mixed> $orderedIds
     */
    protected function onSortChanged(int|string $recordId, string $status, array $orderedIds): void
    {
        $this->persistOrder($status, $orderedIds);
    }

    /**
     * Saves the order of one column. Only visible cards move; hidden ones keep their slots (see PositionWriter).
     *
     * @param array<int, mixed> $orderedIds
     */
    protected function persistOrder(string $status, array $orderedIds): void
    {
        $column = $this->findColumn($status);
        $sort = static::sortAttribute();

        if ($column === null) {
            return;
        }

        if ($sort !== null) {
            $query = $this->getEloquentQuery();
            $query->where($query->getModel()->qualifyColumn(static::$recordStatusAttribute), $column->value);

            PositionWriter::apply($query, $sort, $orderedIds);

            return;
        }

        if (method_exists(static::kanbanModel(), 'setNewOrder')) {
            static::kanbanModel()::setNewOrder(array_values(array_filter($orderedIds, 'is_scalar')));
        }
    }

    /**
     * May this user move this card from one column to another (`$from === $to` for a reorder)?
     * Default: the model's policy `update`, or yes when the model has no policy (like a Filament resource).
     */
    protected function canMove(Model $record, string $from, string $to): bool
    {
        return Gate::getPolicyFor($record) === null || Gate::allows('update', $record);
    }

    /**
     * A reason to refuse this particular move (a transition rule, a WIP limit...), or null to allow it.
     * Runs after canMove() and before the transition form. The text is shown to the user.
     */
    protected function validateMove(Model $record, string $from, string $to): ?string
    {
        return null;
    }

    /**
     * Per-card lock: false pins the card in place (it cannot be picked up, and the server refuses a forged move).
     */
    protected function isRecordDraggable(Model $record): bool
    {
        return true;
    }

    /**
     * Fields to ask for when a card goes from one column to another; null or [] = move straight away.
     *
     * @return array<int, mixed>|null
     */
    protected function columnTransitionSchema(string $from, string $to): ?array
    {
        return null;
    }

    /**
     * Same as columnTransitionSchema() but knows the card; override this one when the form depends on the record.
     *
     * @return array<int, mixed>|null
     */
    protected function transitionSchemaFor(Model $record, string $from, string $to): ?array
    {
        return $this->columnTransitionSchema($from, $to);
    }

    /**
     * Initial values of the transition form.
     *
     * @return array<string, mixed>
     */
    protected function transitionFormDefaults(Model $record, string $from, string $to): array
    {
        return [];
    }

    /**
     * Saves what the transition form collected, inside the move's transaction, after the status is written.
     * Default: fill the record with the form data. Override when the fields are not columns.
     *
     * @param array<string, mixed> $data
     */
    protected function onTransitionConfirmed(Model $record, string $from, string $to, array $data): void
    {
        $record->forceFill($data)->save();
    }

    public function kanbanTransitionAction(): Action
    {
        return Action::make('kanbanTransition')
            ->modalHeading(fn (array $arguments): string => __('filament-kanban::filament-kanban.transition_heading', [
                'from' => $this->findColumn((string) ($arguments['from'] ?? ''))->title ?? '',
                'to' => $this->findColumn((string) ($arguments['to'] ?? ''))->title ?? '',
            ]))
            ->modalSubmitActionLabel(__('filament-kanban::filament-kanban.transition_submit'))
            ->modalWidth('lg')
            ->record(fn (array $arguments): ?Model => $this->kanbanArgumentRecord($arguments))
            ->schema(function (array $arguments): array {
                $record = $this->kanbanArgumentRecord($arguments);

                /** @var array<int, mixed> $schema */
                $schema = $record === null ? [] : ($this->transitionSchemaFor($record, (string) ($arguments['from'] ?? ''), (string) ($arguments['to'] ?? '')) ?? []);

                return $schema;
            })
            ->fillForm(function (array $arguments): array {
                $record = $this->kanbanArgumentRecord($arguments);

                return $record === null ? [] : $this->transitionFormDefaults($record, (string) ($arguments['from'] ?? ''), (string) ($arguments['to'] ?? ''));
            })
            ->action(function (array $arguments, array $data): void {
                $record = $this->kanbanArgumentRecord($arguments);
                $to = (string) ($arguments['to'] ?? '');

                if ($record === null || $this->findColumn($to) === null) {
                    $this->kanbanNotify(__('filament-kanban::filament-kanban.record_missing'), 'warning');

                    return;
                }

                $from = $this->statusOf($record);

                // The card may have changed (or been locked) while the form was open: check again.
                if ($from === $to || !$this->guardMove($record, $from, $to)) {
                    return;
                }

                $this->moveRecord(
                    $record,
                    $from,
                    $to,
                    $this->kanbanIds($arguments['fromOrderedIds'] ?? []),
                    $this->kanbanIds($arguments['toOrderedIds'] ?? []),
                    $data,
                );
            });
    }

    /**
     * @param array<int, mixed>    $fromOrderedIds
     * @param array<int, mixed>    $toOrderedIds
     * @param array<string, mixed> $data
     */
    protected function moveRecord(Model $record, string $from, string $to, array $fromOrderedIds, array $toOrderedIds, array $data = []): void
    {
        $key = $record->getKey();

        DB::transaction(function () use ($key, $from, $to, $fromOrderedIds, $toOrderedIds, $data): void {
            $this->onStatusChanged($key, $to, $fromOrderedIds, $toOrderedIds);

            if ($data !== []) {
                $fresh = $this->findRecord($key);

                if ($fresh !== null) {
                    $this->onTransitionConfirmed($fresh, $from, $to, $data);
                }
            }
        });

        $moved = $this->findRecord($key) ?? $record;

        event(new KanbanRecordMoved($moved, $from, $to, static::class, $data));
        $this->onRecordMoved($moved, $from, $to, $data);
    }

    /**
     * After a successful move (the transaction is committed).
     *
     * @param array<string, mixed> $data
     */
    protected function onRecordMoved(Model $record, string $from, string $to, array $data): void {}

    private function guardMove(Model $record, string $from, string $to): bool
    {
        if (!$this->isRecordDraggable($record) || !$this->canMove($record, $from, $to)) {
            $this->kanbanNotify(__('filament-kanban::filament-kanban.move_denied'), 'danger');

            return false;
        }

        $reason = $this->validateMove($record, $from, $to);

        if ($reason !== null && $reason !== '') {
            $this->kanbanNotify($reason, 'danger');

            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function kanbanArgumentRecord(array $arguments): ?Model
    {
        $key = $arguments['record'] ?? null;

        return is_int($key) || is_string($key) ? $this->findRecord($key) : null;
    }

    /**
     * @return array<int, mixed>
     */
    private function kanbanIds(mixed $ids): array
    {
        return is_array($ids) ? array_values($ids) : [];
    }

    protected function kanbanNotify(string $title, string $level): void
    {
        $notification = Notification::make()->title($title);

        match ($level) {
            'danger' => $notification->danger(),
            'warning' => $notification->warning(),
            default => $notification->success(),
        };

        $notification->send();
    }
}
