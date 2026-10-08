# filament-kanban - spec

Free (MIT) Kanban board for Filament 5. The spiritual successor of `mokhosh/filament-kanban` (Filament 3 only,
unmaintained) plus what a production board needs: reorder that survives filters, transition forms, authorization.

## Scope (1.0)

- A page (`Pages\KanbanBoard`) and a resource page (`Pages\KanbanResourcePage`) that render a model as columns of
  draggable cards. Both are an ordinary Filament page plus the trait `Concerns\InteractsWithKanban`, which can also be
  put on any other page class.
- Columns from a status enum (`Concerns\IsKanbanStatus`, plus Filament `HasLabel` / `HasColor` / `HasIcon`) or from an
  overridable `statuses()` returning arrays (for example rows of a "stages" table).
- Drag between columns and inside a column. The default hooks write the status and the order in one transaction.
- The order is saved for the visible cards only; cards hidden by search or filters keep their slots.
- Optional transition form when a card enters a column; authorization (policy `update` by default); a per-card lock.
- Collapsible columns that still accept drops; empty-column hint; mobile scroll-snap.
- Header search (debounced) over configured attributes.
- Edit modal / slide-over, or a card that is a link.
- Works with int, UUID and ULID keys.
- `make:kanban` and `filament-kanban:install`.

## Public API (what a board author touches)

Static properties (mokhosh names kept): `$model`, `$statusEnum`, `$recordTitleAttribute`, `$recordStatusAttribute`;
new: `$recordSortAttribute` (null = no order is persisted). Views: `$toolbarView`, `$headerView`, `$recordView`,
`$statusView`, `$scriptsView` (static), `$view` (Filament's instance property).

Instance properties: `$collapsedStatuses`, `$collapsibleColumns`, `$searchable`, `$searchableAttributes`,
`$flashSeconds`, and the edit-modal set `$disableEditModal`, `$editModalTitle`, `$editModalWidth`,
`$editModalSlideOver`, `$editModalSaveButtonLabel`, `$editModalCancelButtonLabel`.

Overridable methods, grouped:

| Group | Methods |
| --- | --- |
| Columns | `statuses()`, `columnDefinitions()`, `decorateColumns()` |
| Cards | `getEloquentQuery()`, `modifyRecordsQuery()`, `records()`, `recordRelations()`, `recordTitle()`, `recordDescription()`, `recordUrl()`, `searchableAttributes()` |
| Moving | `onStatusChanged()`, `onSortChanged()`, `onRecordMoved()`, `canMove()`, `validateMove()`, `isRecordDraggable()`, `columnTransitionSchema()`, `transitionSchemaFor()`, `transitionFormDefaults()`, `onTransitionConfirmed()` |
| Editing | `getEditModalFormSchema()`, `getEditModalRecordData()`, `editRecord()`, `canEditRecord()` and the label/width getters |
| Slots | `toolbarExtras()`, `columnHeaderExtras()`, `recordBadges()`, `recordExtras()`, `recordClasses()`, `kanbanViewData()` |

Browser entry points (public by necessity, Livewire listeners): `statusChanged()` (`status-changed`) and
`sortChanged()` (`sort-changed`). Everything that writes is protected.

Value objects: `Support\KanbanColumn` (read-only, array-readable like a mokhosh status), `Events\KanbanRecordMoved`.

## Extension points for an add-on

See [EXTENDING.md](EXTENDING.md). The rule: an add-on subclasses `KanbanBoard` (or uses `InteractsWithKanban`) and
overrides slots; it never copies a view or the move pipeline.

## Non-goals (1.0)

- Swimlanes, WIP limits, transition rules, column summaries, stale/overdue badges, tabs of several boards,
  Filament-table-filter integration: left to the paid add-on `asignua/filament-kanban-pro`, built on the seams above.
- Pagination or lazy loading of a column. A board loads its cards in one query; narrow it with
  `modifyRecordsQuery()`.
- Creating cards from the board (a "new card" button): use a header action on your page.
- Real-time collaboration (other users' moves appearing live): poll or wire your own Echo listener.
- Bundling SortableJS: Filament already ships it as `window.Sortable`.
- A Tailwind build step: the stylesheet is hand-written, namespaced `.fi-kanban*`.
