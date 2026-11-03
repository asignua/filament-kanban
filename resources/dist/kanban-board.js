// Alpine component of the Kanban board. Plain ES module, no build step: Filament loads it on demand (x-load).
// Drag and drop is SortableJS, which Filament already ships as window.Sortable.
export default function kanbanBoard({ storageKey, group, realtime = null }) {
    // Kept out of the returned object on purpose: Alpine would wrap the pusher connection in a reactive proxy.
    let unbindFallback = () => {}

    return {
        collapsed: {},
        dragging: false,

        // Real-time (opt-in): a change made elsewhere is applied once the burst is over, or announced when it cannot be.
        pending: false,
        timer: null,

        init() {
            if (realtime !== null) {
                const onChanged = () => this.changed()

                window.addEventListener('kanban-board-changed', onChanged)

                const stopFallback = this.watchConnection()

                unbindFallback = () => {
                    window.removeEventListener('kanban-board-changed', onChanged)
                    stopFallback()
                }
            }

            try {
                const saved = JSON.parse(window.localStorage.getItem(storageKey) ?? '{}')

                this.collapsed = saved !== null && typeof saved === 'object' ? saved : {}
            } catch (error) {
                this.collapsed = {}
            }
        },

        // Alpine calls this when the board leaves the page (wire:navigate): nothing may outlive it.
        destroy() {
            unbindFallback()
            clearTimeout(this.timer)
        },

        // Weak polling fallback. pusher-js can give up for good after a few abrupt socket drops (a Reverb restart on
        // every deploy): its state turns `failed` / `unavailable` and it never reconnects until a page reload.
        // While that lasts, ask for a refresh every `realtime.fallback_poll` seconds (0 = off, hidden tab skipped).
        // A return to `connected` after a drop does one catch-up: events sent during the outage were lost. The
        // very first initialized -> connecting -> connected is not a drop. Returns the function that unbinds it all.
        watchConnection() {
            const seconds = Number(realtime?.fallback_poll ?? 0)

            if (!(seconds > 0)) {
                return () => {}
            }

            let connection = null
            let poll = null
            let wasDown = false

            const stop = () => {
                clearInterval(poll)
                poll = null
            }

            const down = () => {
                wasDown = true
                poll ??= setInterval(() => {
                    if (!document.hidden) {
                        this.changed()
                    }
                }, seconds * 1000)
            }

            const onState = ({ current }) => {
                if (current === 'failed' || current === 'unavailable') {
                    down()
                } else if (current === 'connected') {
                    stop()

                    if (wasDown) {
                        wasDown = false
                        this.changed()
                    }
                }
            }

            const bind = () => {
                connection = window.Echo?.connector?.pusher?.connection ?? null

                if (connection === null) {
                    return
                }

                connection.bind('state_change', onState)
                onState({ current: connection.state })
            }

            window.Echo ? bind() : window.addEventListener('EchoLoaded', bind, { once: true })

            return () => {
                stop()
                connection?.unbind('state_change', onState)
                window.removeEventListener('EchoLoaded', bind)
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
