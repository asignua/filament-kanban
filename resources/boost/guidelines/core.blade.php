## Filament Kanban (asignua/filament-kanban)

- Boards are ordinary Filament pages; the plugin registration is optional. Generate one: `php artisan make:kanban TasksBoard --model="App\Models\Task" --enum="App\Enums\TaskStatus"`, then register it like any page.
- A board extends `Asignua\FilamentKanban\Pages\KanbanBoard` and sets `protected static string $model`, `$statusEnum` (or overrides `statuses()` to return arrays `['id' => ..., 'title' => ..., 'value' => ..., 'color' => ...]`), `$recordStatusAttribute` (default `status`), `$recordTitleAttribute` and `?string $recordSortAttribute` (the column that stores the manual order; null = order is not saved). On a resource use `Pages\KanbanResourcePage` and list it in `getPages()`.
- Enum: `use Asignua\FilamentKanban\Concerns\IsKanbanStatus;` (Filament `HasLabel` / `HasColor` / `HasIcon` are honoured, `kanbanCases()` limits the cases).
- Put UI filters in `modifyRecordsQuery(Builder)`, never in `getEloquentQuery()`: that one also finds the whole column when positions are saved, and a filter there makes hidden cards collide with visible ones. Only the visible cards are renumbered.
- Moves: `canMove($record, $from, $to)` (default: policy `update`, or allowed without a policy), `validateMove(...)` returns a refusal message or null, `isRecordDraggable($record)` locks a card, `columnTransitionSchema($from, $to)` returns fields for a confirmation modal (submitted data is filled onto the record by `onTransitionConfirmed()`).
- `onStatusChanged()`, `onSortChanged()` write without authorizing - keep them `protected`.
- Cards: `recordBadges()`, `recordExtras()`, `recordClasses()`, `recordUrl()`; header: `columnHeaderExtras()`, `toolbarExtras()`. Details: docs/EXTENDING.md.
- Run `php artisan filament:assets` after install (`filament-kanban:install` does it). Migrating from mokhosh/filament-kanban: swap the namespace; the README lists the differences.
