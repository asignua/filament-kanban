// Alpine component of the Kanban board. Plain ES module, no build step: Filament loads it on demand (x-load).
// Drag and drop is SortableJS, which Filament already ships as window.Sortable.
export default function kanbanBoard({ storageKey, group, realtime = null }) {
    return {
        collapsed: {},
        dragging: false,

        // Real-time (opt-in): a change made elsewhere is applied once the burst is over, or announced when it cannot be.
        pending: false,
        timer: null,

        init() {
            if (realtime !== null) {
                window.addEventListener('kanban-board-changed', () => this.changed())
            }

            try {
                const saved = JSON.parse(window.localStorage.getItem(storageKey) ?? '{}')

                this.collapsed = saved !== null && typeof saved === 'object' ? saved : {}
            } catch (error) {
                this.collapsed = {}
            }
        },

        // Collect a burst of events into one refresh. Never re-render under the user's hands: during a drag or while a
        // modal is open the board stays as it is and the "Board changed" notice offers the refresh instead.
        changed() {
            clearTimeout(this.timer)

            this.timer = setTimeout(() => {
                if (this.dragging || this.modalOpen()) {
                    this.pending = true

                    return
                }

                this.refresh()
            }, realtime?.debounce ?? 500)
        },

        modalOpen() {
            const mounted = this.$wire.mountedActions

            return Array.isArray(mounted) ? mounted.length > 0 : false
        },

        refresh() {
            clearTimeout(this.timer)
            this.pending = false
            this.$wire.$refresh()
        },

        isCollapsed(id, fallback) {
            return Object.prototype.hasOwnProperty.call(this.collapsed, id) ? this.collapsed[id] : fallback
        },

        toggle(id, fallback) {
            this.collapsed = { ...this.collapsed, [id]: !this.isCollapsed(id, fallback) }

            try {
                window.localStorage.setItem(storageKey, JSON.stringify(this.collapsed))
            } catch (error) {
                // Private window or blocked storage: the column still folds, it just is not remembered.
            }
        },

        open(id) {
            if (this.dragging) {
                return
            }

            this.$wire.mountAction('kanbanEdit', { record: id })
        },

        sortable(container) {
            if (container.sortable) {
                return
            }

            if (!window.Sortable) {
                console.warn('filament-kanban: window.Sortable is missing, drag and drop is off.')

                return
            }

            const ids = (list) =>
                Array.from(list.querySelectorAll(':scope > [data-kanban-record]')).map((card) => card.dataset.kanbanRecord)

            container.sortable = new window.Sortable(container, {
                group,
                draggable: '[data-kanban-record]',
                filter: '[data-locked]',
                preventOnFilter: false,
                animation: 150,
                delay: 150,
                delayOnTouchOnly: true,
                touchStartThreshold: 5,
                ghostClass: 'fi-kanban-ghost',
                chosenClass: 'fi-kanban-chosen',
                onStart: () => {
                    this.dragging = true
                    document.body.classList.add('fi-kanban-grabbing')
                },
                onEnd: (event) => {
                    document.body.classList.remove('fi-kanban-grabbing')
                    // The click that ends a drag must not open the card.
                    setTimeout(() => (this.dragging = false), 0)

                    const recordId = event.item.dataset.kanbanRecord

                    if (event.to === event.from) {
                        if (event.oldIndex === event.newIndex) {
                            return
                        }

                        this.$wire.sortChanged(recordId, event.to.dataset.statusId, ids(event.to))

                        return
                    }

                    this.$wire.statusChanged(recordId, event.to.dataset.statusId, ids(event.from), ids(event.to))
                },
            })
        },
    }
}
