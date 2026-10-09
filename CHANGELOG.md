# Changelog

All notable changes to `asignua/filament-kanban` are documented here.

## v1.0.0 - unreleased

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
