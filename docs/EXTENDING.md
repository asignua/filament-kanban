# Extending filament-kanban

Everything below is a protected method or property of the board page (the trait `Concerns\InteractsWithKanban`).
An add-on, or your own base class, overrides them; nothing needs a copy of a view or of the move pipeline.

```php
abstract class MyBoard extends \Asignua\FilamentKanban\Pages\KanbanBoard
{
    // shared behaviour for all your boards
}
```

A board that cannot extend `KanbanBoard` (it already extends something else) uses the trait:

```php
class MyPage extends SomeBasePage
{
    use \Asignua\FilamentKanban\Concerns\InteractsWithKanban;

    protected string $view = 'filament-kanban::kanban-board';
}
```

## Slots (markup)

| Seam | Signature | Where it renders |
| --- | --- | --- |
| Toolbar | `toolbarExtras(): array<string\|Htmlable>` | next to the search box |
| Column header | `columnHeaderExtras(KanbanColumn $column): array<string\|Htmlable>` | after the card counter |
| Card badges | `recordBadges(Model $record, KanbanColumn $column): array<array{label, color?, icon?, tooltip?}>` | under the title, as Filament badges |
| Card footer | `recordExtras(Model $record, KanbanColumn $column): array<string\|Htmlable>` | bottom of the card |
| Card classes | `recordClasses(Model $record, KanbanColumn $column): string` | on the `<article>` (for example `is-overdue`) |

A `string` is escaped, an `Htmlable` is printed as is. Ship your CSS as a Filament asset; the board's own classes are all
`.fi-kanban*`, and a column / card carries `data-column` / `data-kanban-record`.

## Data

- `decorateColumns(Collection<KanbanColumn> $columns)` runs on the finished columns (cards attached). Return them with
  `->withMeta(['limit' => 5, 'sum' => '1 200'])`; `KanbanColumn::$meta` is yours, the base never reads it. Read it back in
  `columnHeaderExtras()`.
- `columnDefinitions()` builds the columns before cards are attached (cached for the request). Override to add, remove or
  reorder columns for a user.
- `kanbanViewData()` is what the board view receives (`columns`, and `statuses` for mokhosh views). Add keys (lanes,
  tabs) and point `$view` at a view that includes the base partials.

## Queries

- `getEloquentQuery()`: the model's scopes only. It is also used to find a whole column when positions are saved, so
  **never put UI filters here** - a filter would hide cards from the renumbering and they would collide with visible ones.
- `modifyRecordsQuery(Builder)`: search, filters, "only mine". This is where a table-filter integration plugs in.
- `recordRelations()`: eager loads.
- `records()`: replaces the whole fetch (search and ordering too). Prefer the three above.

## Moving

Order of events for a drop into another column:

1. `findRecord()` and the column are resolved (otherwise a warning).
2. `isRecordDraggable()` and `canMove($record, $from, $to)` (security; default: policy `update`, or yes when no policy).
3. `validateMove($record, $from, $to): ?string` - **before-move validation**: return a message to refuse, `null` to allow
   (transition rules, WIP limits).
4. `transitionSchemaFor()` -> `columnTransitionSchema($from, $to)`: fields to ask for, or `null`.
5. In one transaction: `onStatusChanged()` (status + order), then `onTransitionConfirmed()` when a form was shown.
6. After commit: the `Events\KanbanRecordMoved` event and `onRecordMoved($record, $from, $to, $data)`.

A reorder inside a column goes through `isRecordDraggable()`, `canMove($record, $s, $s)` and `onSortChanged()`.

`onStatusChanged`, `onSortChanged`, `persistOrder` are protected: they write without asking. Keep them protected in
your overrides unless you re-check authorization inside.

## What the paid add-on builds on

| Feature | Seams used |
| --- | --- |
| WIP limits | `decorateColumns` (limit in meta), `columnHeaderExtras` (n / limit), `validateMove` (refuse when full) |
| Transition rules | `validateMove`, `columnTransitionSchema` (a reason field per allowed edge) |
| Column summaries | `decorateColumns` (sum in meta), `columnHeaderExtras` |
| Stale / overdue badges | `recordBadges`, `recordClasses` |
| Table-filter integration | `modifyRecordsQuery`, `toolbarExtras` |
| Multiple boards as tabs | one subclass per tab sharing a trait; `kanbanViewData` for the tab list; `$search` is a public property per page |
| Swimlanes | `kanbanViewData` + own `$view`/`$statusView`; see "Known gaps" |

## Known gaps

- The browser reports the destination **column id** only. A swimlane board needs the lane too (a drop into lane B of
  column "Doing"); until the drop payload can carry extra values, a lane add-on must encode lane and column in the
  container's `data-status-id` and split them in `statusChanged()`.
- Several boards on one page are not supported (the Sortable group is per page class).
