<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Concerns;

use Asignua\FilamentKanban\Events\KanbanRecordMoved;
use Asignua\FilamentKanban\Support\KanbanGate;
use Asignua\FilamentKanban\Support\PositionWriter;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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
    /** @var array<string, mixed> Answers of the transition modal while a move is being written. */
    protected array $kanbanTransitionData = [];

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

        // Start at the bottom of the new column: the old column's number means nothing here and could tie with
        // (or undercut) a hidden card's slot.
        $sort = $this->effectiveSortAttribute();

        if ($sort !== null) {
            $query = $this->getEloquentQuery();
            $model = $query->getModel();
            $max = $query
                ->reorder()
                ->where($model->qualifyColumn(static::$recordStatusAttribute), $column->value)
                ->where($model->qualifyColumn($model->getKeyName()), '!=', $record->getKey())
                ->max($model->qualifyColumn($sort));

            $record->setAttribute($sort, (is_numeric($max) ? (int) $max : 0) + 1);
        }

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
        $sort = $this->effectiveSortAttribute();

        if ($column === null || $sort === null) {
            return;
        }

        $query = $this->getEloquentQuery();
        $query->where($query->getModel()->qualifyColumn(static::$recordStatusAttribute), $column->value);

        // Ids from the browser are intersected with this column inside PositionWriter: foreign ids are ignored.
        PositionWriter::apply($query, $sort, $orderedIds);
    }

    /**
     * The column that holds the order: `$recordSortAttribute`, else the order column of a spatie/eloquent-sortable model.
     */
    protected function effectiveSortAttribute(): ?string
    {
        $sort = static::sortAttribute();

        if ($sort !== null) {
            return $sort;
        }

        $model = static::kanbanModel();

        if (method_exists($model, 'setNewOrder') && method_exists($model, 'determineOrderColumnName')) {
            $column = (new $model)->determineOrderColumnName();

            return is_string($column) ? $column : null;
        }

        return null;
    }

    /**
     * May this user move this card from one column to another (`$from === $to` for a reorder)?
     * Default: the model's policy `update`, or yes when the model has no policy (like a Filament resource).
     */
    protected function canMove(Model $record, string $from, string $to): bool
    {
        return KanbanGate::allows($record);
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
                'from' => $this->findColumn($this->kanbanRealFrom($arguments))->title ?? '',
                'to' => $this->findColumn((string) ($arguments['to'] ?? ''))->title ?? '',
            ]))
            ->modalSubmitActionLabel(__('filament-kanban::filament-kanban.transition_submit'))
            ->modalWidth('lg')
            ->record(fn (array $arguments): ?Model => $this->kanbanArgumentRecord($arguments))
            ->schema(function (array $arguments): array {
                $record = $this->kanbanArgumentRecord($arguments);

                /** @var array<int, mixed> $schema */
                $schema = $record === null ? [] : ($this->transitionSchemaFor($record, $this->statusOf($record), (string) ($arguments['to'] ?? '')) ?? []);

                return $schema;
            })
            ->fillForm(function (array $arguments): array {
                $record = $this->kanbanArgumentRecord($arguments);

                return $record === null ? [] : $this->transitionFormDefaults($record, $this->statusOf($record), (string) ($arguments['to'] ?? ''));
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

        $previous = $this->kanbanTransitionData;
        $this->kanbanTransitionData = $data;

        try {
            DB::transaction(function () use ($key, $from, $to, $fromOrderedIds, $toOrderedIds, $data): void {
                $this->onRecordTransitioned($key, $from, $to, $fromOrderedIds, $toOrderedIds, $data);
            });
        } finally {
            $this->kanbanTransitionData = $previous;
        }

        $moved = $this->findRecord($key) ?? $record;

        event(new KanbanRecordMoved($moved, $from, $to, static::class, $data));
        $this->onRecordMoved($moved, $from, $to, $data);
    }

    /**
     * The single write hook of a move, called inside the move's transaction after canMove()/validateMove() passed.
     * `$data` holds the transition modal's answers ([] for a plain drag). Default: onStatusChanged() (status + order),
     * then onTransitionConfirmed() when there are answers. Override it when ONE write service needs the move and the
     * answers in the same call; the existing hooks keep working untouched.
     *
     * @param array<int, mixed>    $fromOrderedIds
     * @param array<int, mixed>    $toOrderedIds
     * @param array<string, mixed> $data
     */
    protected function onRecordTransitioned(int|string $recordId, string $from, string $to, array $fromOrderedIds, array $toOrderedIds, array $data): void
    {
        $this->onStatusChanged($recordId, $to, $fromOrderedIds, $toOrderedIds);

        if ($data !== []) {
            $fresh = $this->findRecord($recordId);

            if ($fresh !== null) {
                $this->onTransitionConfirmed($fresh, $from, $to, $data);
            }
        }
    }

    /**
     * The transition modal's answers while a move is being written (also readable from onStatusChanged());
     * [] outside a move and for a plain drag.
     *
     * @return array<string, mixed>
     */
    protected function currentTransitionData(): array
    {
        return $this->kanbanTransitionData;
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
     * The column the card really is in: the `from` argument comes from the browser and is never trusted.
     *
     * @param array<string, mixed> $arguments
     */
    private function kanbanRealFrom(array $arguments): string
    {
        $record = $this->kanbanArgumentRecord($arguments);

        return $record === null ? '' : $this->statusOf($record);
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
