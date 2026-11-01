# Changelog

All notable changes to `asignua/filament-kanban` are documented here.

## v1.1.1 - 2026-11-01
- Lighter cards: a card no longer renders a pair of Livewire morph markers for every optional part it does not use (one dynamic title tag instead of an `@if` chain, the lock hint and `data-locked` as echoes, description / badges / extras behind one condition). A 125-card board went from 89 KB to 66 KB of HTML (500 cards: 312 KB to 218 KB), and so did every Livewire update after a drag. The markup a card produces is the same. The snapshot never held the cards (about 1 KB at any size); `PageWeightTest` keeps it that way.
- Docs: the migration section from `mokhosh/filament-kanban` now has a full API mapping table; `MokhoshMigrationTest` runs a board written as their README shows it (public hooks, hand-built `records()` / `statuses()`).
- Docs: the Free-vs-Pro table leads with the workflow designer, transition rules, WIP limits, totals and several boards.
- Fix: the "Show more" spinner of a lazy column sent `loadMoreRecords(@js(...))` to the browser uncompiled, so Alpine logged an expression error on every click. Verified on Filament 5.11.0.

## v1.1.0 - 2026-10-25
- Lazy columns (opt-in): `protected ?int $recordsPerColumn` or `recordsPerColumn(KanbanColumn)` makes a column load one page of cards with a "Show more" button (`loadMoreRecords()`). The header badge shows the column's total (search applied). Default `null` loads everything as before; a board that overrides `records()` is never paged.
- Dropping a card into a lazy column places it right behind the last loaded card (works with tied / NULL positions and custom write hooks) and widens the page by one. `PositionWriter` renumbers with bulk CASE updates and orders NULL positions first on every database.
- New seams: `filteredRecordsQuery()`, `columnRecordsQuery()`, `KanbanColumn::total()` / `hiddenCount()` / `withTotal()`. Translation keys `show_more`, `hidden_count`.

## v1.0.1 - 2026-10-12

- Docs: screenshots, cover and a workbench demo.

## v1.0.0 - 2026-10-09

First release.

- `Pages\KanbanBoard` and `Pages\KanbanResourcePage`, both built from the trait `Concerns\InteractsWithKanban`.
- Columns from an enum (`Concerns\IsKanbanStatus`, `HasLabel` / `HasColor` / `HasIcon` honoured) or from an overridden `statuses()`.
- Drag and drop with the SortableJS that Filament already ships; hooks `onStatusChanged()` / `onSortChanged()` with mokhosh-compatible signatures, writing status and order in a transaction.
- Order is saved for the visible cards only; cards hidden by search or filters keep their slots.
- `onRecordTransitioned()` single write hook (receives the transition modal's answers; default = `onStatusChanged()` + `onTransitionConfirmed()`) and `currentTransitionData()`.
- Transition modal (`columnTransitionSchema()`), authorization (`canMove()`, policy `update` by default), per-card lock (`isRecordDraggable()`), before-move validation (`validateMove()`).
- Collapsible columns that still accept drops, empty-column hint, recently-updated flash, debounced search, mobile scroll-snap.
- Edit modal / slide-over (`getEditModalFormSchema()`, `editRecord()`) or a card that links (`recordUrl()`).
- Int, UUID and ULID keys; spatie/eloquent-sortable style models (`ordered()` / `setNewOrder()`) still work.
- Slots for add-ons: toolbar, column header, card badges, card footer, card classes, column meta, query modification.
- `make:kanban`, `filament-kanban:install`.
- Translations: English, Ukrainian, German, Spanish, French, Italian, Dutch, Polish, Brazilian Portuguese, Turkish. Laravel Boost guidelines.
