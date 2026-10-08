# Filament Kanban

[![Tests](https://img.shields.io/github/actions/workflow/status/asignua/filament-kanban/tests.yml?branch=main&label=tests)](https://github.com/asignua/filament-kanban/actions/workflows/tests.yml)

A drag-and-drop Kanban board for Filament 5. Columns come from an enum or from database rows, cards are any Eloquent model
(int, UUID or ULID keys), and the order is saved the way a real team board needs it: **cards hidden by a search or a filter
keep their places**. Moving a card can ask a question first (a lost reason, a comment), is authorized through your policy,
and can be refused by your own rule. It is the successor of `mokhosh/filament-kanban`, which stopped at Filament 3, with the
same class names and hooks so a board moves over by swapping a namespace.

No JavaScript to build, no Tailwind safelist: drag and drop uses the SortableJS Filament already ships, the stylesheet is
namespaced (`.fi-kanban*`) and uses Filament's own colour variables, so it follows your panel colours and dark mode.

## Screenshots

TODO: add images to `art/` (cover.jpg first), then reference them here: the board, a collapsed column, the transition
modal, the mobile view, dark mode.

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- Filament 5

## Installation

```bash
composer require asignua/filament-kanban
php artisan filament:assets
```

`php artisan filament-kanban:install` does the second step too. The plugin class is optional - boards are ordinary pages:

```php
use Asignua\FilamentKanban\KanbanPlugin;

$panel->plugin(KanbanPlugin::make());
```

## Usage

### 1. An enum-driven board

```php
use Asignua\FilamentKanban\Concerns\IsKanbanStatus;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TaskStatus: string implements HasColor, HasIcon, HasLabel
{
    use IsKanbanStatus;

    case Todo = 'todo';
    case Doing = 'doing';
    case Done = 'done';

    public function getLabel(): string { return ucfirst($this->value); }
    public function getColor(): string { return match ($this) { self::Todo => 'gray', self::Doing => 'warning', self::Done => 'success' }; }
    public function getIcon(): string { return 'heroicon-m-bolt'; }
}
```

`HasLabel`, `HasColor` and `HasIcon` are optional; without them the column title is the case value. Override
`kanbanCases()` to keep some cases off the board, `getTitle()` to change a title.

```bash
php artisan make:kanban TasksBoard --model="App\Models\Task" --enum="App\Enums\TaskStatus"
```

```php
use Asignua\FilamentKanban\Pages\KanbanBoard;

class TasksBoard extends KanbanBoard
{
    protected static string $model = Task::class;

    protected static string $statusEnum = TaskStatus::class;

    protected static string $recordStatusAttribute = 'status';   // default

    protected static string $recordTitleAttribute = 'title';     // default

    protected static ?string $recordSortAttribute = 'position';  // where the manual order is stored; null = not stored
}
```

Cast the status column to the enum on the model (`'status' => TaskStatus::class`); a plain string column works too.

### 2. Columns from the database

Override `statuses()` and return arrays. `id` is the column id (what the browser sends), `value` is what is written to the
status attribute (defaults to `id`):

```php
protected static string $recordStatusAttribute = 'stage_id';

protected function statuses(): Collection
{
    return Stage::query()->orderBy('position')->get()->map(fn (Stage $stage): array => [
        'id' => (string) $stage->id,
        'title' => $stage->name,
        'value' => $stage->id,
        'color' => $stage->color,   // optional: a Filament colour name
        'icon' => null,             // optional
        'collapsed' => false,       // optional
    ]);
}
```

### 3. On a resource

```php
class TasksKanban extends \Asignua\FilamentKanban\Pages\KanbanResourcePage
{
    protected static string $resource = TaskResource::class;

    protected static string $statusEnum = TaskStatus::class;
}

// TaskResource::getPages(): 'kanban' => TasksKanban::route('/kanban')
```

The model and the base query (tenancy, soft-delete scopes) come from the resource. Any other page class can use the trait
`Asignua\FilamentKanban\Concerns\InteractsWithKanban` instead.

### Order of cards

With `$recordSortAttribute` set, the board sorts by that column and saves the order after every drop. **Only the visible
cards change places.** Search "invoice", drag one match above another: the matches swap slots, the hidden cards keep the
slots they had. Writes use the query builder, so there are no model events and `updated_at` is not touched (a touch would
make every renumbered card flash).

Without it nothing is persisted (cards are shown by key). If the model uses `spatie/eloquent-sortable`, its `ordered()`
scope and `setNewOrder()` are used, like in mokhosh/filament-kanban.

### Search and filters

A debounced search box filters the cards by `$recordTitleAttribute`; set `protected array $searchableAttributes = ['title', 'client.name']`
(dotted = through a relation) or `protected bool $searchable = false`. The term is also in the URL (`?search=`).

For your own filters override `modifyRecordsQuery(Builder $query): Builder`. Do **not** put filters in `getEloquentQuery()`:
that query also finds the whole column when positions are saved.

### Asking before a move

```php
use Filament\Forms\Components\Textarea;

protected function columnTransitionSchema(string $from, string $to): ?array
{
    return $to === 'lost' ? [Textarea::make('lost_reason')->required()] : null;
}
```

When the schema is not empty, dropping a card into that column opens a Filament modal instead of moving it. **Confirm** moves
the card and fills the form data onto the record (`onTransitionConfirmed()`, override it when the fields are not columns).
The card is not moved until you confirm: while the modal is open the board re-renders and shows the card in its original column, and **Confirm** moves it. **Cancel** just closes the modal; nothing changed. Need the record in the form? Override
`transitionSchemaFor(Model $record, string $from, string $to)` and `transitionFormDefaults(...)`.

### Who may move what

- `canMove(Model $record, string $from, string $to): bool` - default: the model's policy `update`, and allowed when the model
  has no policy (like a Filament resource). Denied: a danger notification, the card jumps back. `$from === $to` for a reorder.
- `validateMove(Model $record, string $from, string $to): ?string` - return a message to refuse a move for a business reason.
- `isRecordDraggable(Model $record): bool` - lock a card (it cannot be picked up, and a forged request is refused too).
- `canEditRecord(Model $record): bool` - same for the edit modal. A board whose cards are links (`recordUrl()`) or that sets `$disableEditModal` exposes no edit action at all.
- With `->strictAuthorization()` on the panel, a model **without** a policy is refused (move and edit), like a resource.
- A standalone board checks the model's `viewAny` policy in `canAccess()` (override it for your own rule); for per-row visibility use `modifyRecordsQuery()`.
- A reorder is authorized for the dragged card only. Other cards of the column are renumbered around it, including locked ones, so
  a locked card can change its stored position (not its column) when a neighbour is dragged past it.

### Columns

All columns can be folded with the button in the header; a folded column is a narrow strip that still accepts drops. Fold
some at start with `protected array $collapsedStatuses = ['done']` (or `'collapsed' => true` in a status array). The choice is
remembered per browser. Turn the button off with `protected bool $collapsibleColumns = false`. An empty column shows a drop
hint. On a phone the columns scroll horizontally and snap.

### Cards

By default a card shows the title; clicking it opens the edit modal. Override:

```php
protected function recordTitle(Model $record): string;                       // default: the title attribute
protected function recordDescription(Model $record): ?string;                // grey line under the title
protected function recordBadges(Model $record, KanbanColumn $column): array; // [['label' => 'Overdue', 'color' => 'danger', 'icon' => 'heroicon-m-clock']]
protected function recordExtras(Model $record, KanbanColumn $column): array; // free markup (Htmlable)
protected function recordUrl(Model $record): ?string;                        // the card becomes a link, no modal
protected function recordRelations(): array;                                 // eager loads
```

A card that was updated in the last `$flashSeconds` (default 3) pulses once.

### Edit modal

```php
protected function getEditModalFormSchema(int|string|null $recordId): array
{
    return [TextInput::make('title')->required(), Textarea::make('notes')];
}

protected function editRecord(int|string $recordId, array $data, array $state): void
{
    Task::find($recordId)?->update($data);   // default: forceFill($data)->save()
}

protected string $editModalTitle = 'Edit task';
protected string $editModalWidth = '2xl';
protected bool $editModalSlideOver = true;
protected string $editModalSaveButtonLabel = 'Save';
protected string $editModalCancelButtonLabel = 'Cancel';
public bool $disableEditModal = false;
```

### Views

Publish with `php artisan vendor:publish --tag=filament-kanban-views`, or point one board at its own view:

```php
protected static string $toolbarView = 'my-board.toolbar';
protected static string $headerView = 'my-board.header';   // a column header
protected static string $statusView = 'my-board.column';
protected static string $recordView = 'my-board.card';
protected static string $scriptsView = 'my-board.scripts'; // empty by default
protected string $view = 'my-board.page';
```

The column view receives `$column` (a read-only `KanbanColumn`: `id`, `title`, `value`, `color`, `icon`, `records`, `meta`;
also readable as `$column['title']`), the card view `$record` and `$column`.

### Events

`Asignua\FilamentKanban\Events\KanbanRecordMoved` (`record`, `from`, `to`, `board`, `data`) is dispatched after a card changed
column, and `onRecordMoved()` is called on the board. Reorders inside a column do not fire it.

## Configuration

There is no config file: everything is a property or an overridable method on the board (see above and
[docs/SPEC.md](docs/SPEC.md)). The translations are published with `--tag=filament-kanban-translations`.

## Migrating from mokhosh/filament-kanban

1. `composer remove mokhosh/filament-kanban && composer require asignua/filament-kanban`.
2. Swap the namespaces: `Mokhosh\FilamentKanban\Pages\KanbanBoard` -> `Asignua\FilamentKanban\Pages\KanbanBoard`,
   `Mokhosh\FilamentKanban\Concerns\IsKanbanStatus` -> `Asignua\FilamentKanban\Concerns\IsKanbanStatus`.
3. Fix the Filament 4/5 differences in your board class (these are Filament's, not ours):
   `protected static string|BackedEnum|null $navigationIcon`, `protected string $view` is no longer static,
   form components come from `Filament\Forms\Components`, layout from `Filament\Schemas\Components`.
4. Run `php artisan filament:assets` (or `filament-kanban:install`, the same command name) and delete the old published assets.
5. Delete a published `kanban-*` view unless you changed it on purpose; the old markup uses Tailwind classes your panel
   theme does not compile.

What stays the same: `$model`, `$statusEnum`, `$recordTitleAttribute`, `$recordStatusAttribute`, `statuses()` returning
`id`/`title` arrays, `records()`, `getEloquentQuery()`, `onStatusChanged()` / `onSortChanged()` with the same signatures,
`getEditModalFormSchema()`, `editRecord()`, the `$editModal*` properties and `$disableEditModal`, the five `*View` properties,
the Livewire events `status-changed` / `sort-changed`, `make:kanban`, `filament-kanban:install`, and views reading
`$statuses` / `$status['records']`.

What differs:

- **The hooks are protected.** In mokhosh `onStatusChanged()` is public, which means any user can call it from the browser
  console and skip your checks. Here the browser calls `statusChanged()` / `sortChanged()`, which authorize and validate, and
  then call the (protected) hooks. If you override a hook as `public`, you expose it again.
- The order is saved with a column you name (`$recordSortAttribute`), visible-only. `spatie/eloquent-sortable` models still work
  when no sort attribute is set.
- A move is checked against the model's policy (`update`) - a model with a policy that denies `update` can no longer be moved.
- `getEditModalFormSchema()` now feeds a Filament action modal (`kanbanEdit`), so the form is an ordinary action schema.
- New: transition modal, `canMove()` / `validateMove()` / `isRecordDraggable()`, search, collapsible columns, `recordUrl()`,
  badges and slots, ULID/UUID keys, the resource page.
- Not ported: the deprecated `HasRecentUpdateIndication` trait (the flash is built in) and the `TestsFilamentKanban` testing macros.

## Gotchas

- **A filter in `getEloquentQuery()` corrupts the order.** The board finds the whole column through that query when it saves
  positions. A hidden card then does not exist for it, and its position collides with a visible one. Filters go to `modifyRecordsQuery()`.
- **Public hooks are callable from the browser.** Keep `on*` hooks protected, authorize in `canMove()`.
- **Overriding `records()` replaces the search and the ordering.** Use `modifyRecordsQuery()` unless you build the collection by hand.
- **`statuses()` ids are strings.** A numeric key is compared as a string; put the real value in `value` so the attribute gets an int.
- **Run `php artisan filament:assets` after install and after an update.** The board's script and stylesheet are Filament
  assets; a stale published copy gives a board that renders but does not drag.
- **Column cards come from one query.** A column with thousands of cards is slow; narrow `modifyRecordsQuery()` (for example by date).
- Search uses `LIKE %term%`; `%` and `_` typed by the user act as wildcards.

## Extending

Slots for toolbar, column headers, card badges and footers, column meta, query modification and before-move validation are
documented in [docs/EXTENDING.md](docs/EXTENDING.md). The paid add-on `asignua/filament-kanban-pro` (swimlanes, WIP limits,
transition rules, column summaries, stale badges, boards as tabs, table-filter integration) is built only on these.

## Translations

The interface ships in English, Ukrainian, German, Spanish, French, Italian, Dutch, Polish, Brazilian Portuguese and Turkish
under the `filament-kanban::filament-kanban` namespace. A test keeps every language in step with the English keys. Override a
string by publishing the translations (`--tag=filament-kanban-translations`) and editing the copy in
`lang/vendor/filament-kanban`.

## AI agents

The package ships [Laravel Boost](https://laravel.com/docs/boost) guidelines (`resources/boost/guidelines/core.blade.php`) so a
coding agent wires it up correctly.

## Testing

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/pint --test
```

Try the demo boards by hand: `vendor/bin/testbench workbench:build`, then `vendor/bin/testbench serve --host=0.0.0.0`,
log in at `/admin` as `emma@example.com` / `password`.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
