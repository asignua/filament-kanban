<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Concerns;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Clicking a card: either a link (recordUrl()) or an edit modal / slide-over built from a form schema.
 * Property and method names follow mokhosh/filament-kanban so a board migrates without renaming.
 */
trait EditsKanbanRecords
{
    public bool $disableEditModal = false;

    protected string $editModalTitle = '';

    protected bool $editModalSlideOver = false;

    protected string $editModalWidth = '2xl';

    protected string $editModalSaveButtonLabel = '';

    protected string $editModalCancelButtonLabel = '';

    public function kanbanEditAction(): Action
    {
        return Action::make('kanbanEdit')
            ->modalHeading($this->getEditModalTitle())
            ->slideOver($this->getEditModalSlideOver())
            ->modalWidth($this->getEditModalWidth())
            ->modalSubmitActionLabel($this->getEditModalSaveButtonLabel())
            ->modalCancelActionLabel($this->getEditModalCancelButtonLabel())
            ->record(fn (array $arguments): ?Model => $this->kanbanEditRecord($arguments))
            ->visible(fn (array $arguments): bool => !$this->disableEditModal && $this->kanbanMayEdit($arguments))
            ->schema(fn (array $arguments): array => $this->getEditModalFormSchema($this->kanbanRecordId($arguments)))
            ->fillForm(function (array $arguments): array {
                $id = $this->kanbanRecordId($arguments);
                $record = $id === null ? null : $this->findRecord($id);

                return $record === null ? [] : $this->getEditModalRecordData($id, $record->attributesToArray());
            })
            ->action(function (array $arguments, array $data): void {
                $id = $this->kanbanRecordId($arguments);
                $record = $id === null ? null : $this->findRecord($id);

                if ($id === null || $record === null || !$this->kanbanMayEdit($arguments)) {
                    $this->kanbanNotify(__('filament-kanban::filament-kanban.move_denied'), 'danger');

                    return;
                }

                $this->editRecord($id, $data, $record->attributesToArray());
            });
    }

    /**
     * Form fields of the edit modal. `$recordId` is the key of the card being edited.
     *
     * @return array<int, mixed>
     */
    protected function getEditModalFormSchema(int|string|null $recordId): array
    {
        return [
            TextInput::make(static::$recordTitleAttribute),
        ];
    }

    /**
     * What the form starts with. Default: the record's attributes.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function getEditModalRecordData(int|string $recordId, array $data): array
    {
        return $data;
    }

    /**
     * Saves the edit form. `$data` is the validated form data, `$state` the record's attributes before the edit.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $state
     */
    protected function editRecord(int|string $recordId, array $data, array $state): void
    {
        $this->findRecord($recordId)?->forceFill($data)->save();
    }

    /**
     * May this user open the edit modal for this card? Default: the model's policy `update` (yes without a policy).
     */
    protected function canEditRecord(Model $record): bool
    {
        return Gate::getPolicyFor($record) === null || Gate::allows('update', $record);
    }

    protected function getEditModalTitle(): string
    {
        return $this->editModalTitle !== '' ? $this->editModalTitle : __('filament-kanban::filament-kanban.edit_heading');
    }

    protected function getEditModalSlideOver(): bool
    {
        return $this->editModalSlideOver;
    }

    protected function getEditModalWidth(): string
    {
        return $this->editModalWidth;
    }

    protected function getEditModalSaveButtonLabel(): string
    {
        return $this->editModalSaveButtonLabel !== '' ? $this->editModalSaveButtonLabel : __('filament-kanban::filament-kanban.edit_save');
    }

    protected function getEditModalCancelButtonLabel(): string
    {
        return $this->editModalCancelButtonLabel !== '' ? $this->editModalCancelButtonLabel : __('filament-kanban::filament-kanban.edit_cancel');
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function kanbanRecordId(array $arguments): int|string|null
    {
        $id = $arguments['record'] ?? null;

        return is_int($id) || is_string($id) ? $id : null;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function kanbanEditRecord(array $arguments): ?Model
    {
        $id = $this->kanbanRecordId($arguments);

        return $id === null ? null : $this->findRecord($id);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function kanbanMayEdit(array $arguments): bool
    {
        $record = $this->kanbanEditRecord($arguments);

        // No record in the arguments yet (Filament resolves the action once while registering it): do not hide it.
        return $record === null ? !isset($arguments['record']) : $this->canEditRecord($record);
    }
}
