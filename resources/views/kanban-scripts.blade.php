{{--
    Extension point, empty by default. The board's behaviour (Sortable, collapsing, opening a card) lives in the
    `kanbanBoard` Alpine component, so there is no inline <script> to keep in step. Override `$scriptsView` on a board
    to add markup or Alpine bindings inside the board root (it can call open(id), toggle(id) and sortable(el)).
--}}
