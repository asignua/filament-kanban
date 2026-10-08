<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Support;

use ArrayAccess;
use BackedEnum;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use LogicException;

/**
 * One column of the board: an id (always a string, it travels through the DOM), a title, the raw value that is
 * written to the status attribute when a card is dropped here, and the cards that currently sit in it.
 *
 * It is read-only and also readable like mokhosh/filament-kanban's status array (`$column['id']`,
 * `$column['title']`, `$column['records']`), so views published for that package keep working.
 *
 * @implements ArrayAccess<string, mixed>
 */
final readonly class KanbanColumn implements ArrayAccess
{
    /**
     * @param Collection<int, Model>                $records
     * @param array<int|string, string>|string|null $color
     * @param array<string, mixed>                  $meta    Free room for add-ons (WIP limit, swimlane key, summaries...).
     */
    public function __construct(
        public string $id,
        public string $title,
        public mixed $value = null,
        public string|array|null $color = null,
        public string|BackedEnum|null $icon = null,
        public bool $collapsed = false,
        public Collection $records = new EloquentCollection,
        public array $meta = [],
    ) {}

    /**
     * Accepts mokhosh-style status arrays (`id`, `title`) plus `value`, `color`, `icon`, `collapsed`, `meta`.
     *
     * @param array<string, mixed> $status
     */
    public static function fromArray(array $status): self
    {
        $id = $status['id'] ?? null;

        if (!is_scalar($id) || (string) $id === '') {
            throw new LogicException('A kanban status needs a non-empty "id".');
        }

        $title = $status['title'] ?? $status['label'] ?? null;
        $color = $status['color'] ?? null;
        $icon = $status['icon'] ?? null;
        $meta = $status['meta'] ?? [];

        return new self(
            id: (string) $id,
            title: is_scalar($title) ? (string) $title : (string) $id,
            value: array_key_exists('value', $status) ? $status['value'] : $id,
            color: is_string($color) || is_array($color) ? $color : null,
            icon: is_string($icon) || $icon instanceof BackedEnum ? $icon : null,
            collapsed: (bool) ($status['collapsed'] ?? false),
            meta: is_array($meta) ? $meta : [],
        );
    }

    /**
     * @param Collection<int, Model> $records
     */
    public function withRecords(Collection $records): self
    {
        return new self($this->id, $this->title, $this->value, $this->color, $this->icon, $this->collapsed, $records, $this->meta);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function withMeta(array $meta): self
    {
        return new self($this->id, $this->title, $this->value, $this->color, $this->icon, $this->collapsed, $this->records, [...$this->meta, ...$meta]);
    }

    public function count(): int
    {
        return $this->records->count();
    }

    /**
     * The Filament colour name when the colour is a plain name (it then maps to the panel's `fi-color-*` class).
     */
    public function colorName(): ?string
    {
        return is_string($this->color) && preg_match('/^[a-z][a-z0-9_-]*$/i', $this->color) === 1 ? $this->color : null;
    }

    public function offsetExists(mixed $offset): bool
    {
        return in_array($offset, ['id', 'title', 'value', 'color', 'icon', 'collapsed', 'records', 'meta'], true);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return match ($offset) {
            'id' => $this->id,
            'title' => $this->title,
            'value' => $this->value,
            'color' => $this->color,
            'icon' => $this->icon,
            'collapsed' => $this->collapsed,
            'records' => $this->records,
            'meta' => $this->meta,
            default => null,
        };
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('A kanban column is read-only; use withRecords() / withMeta().');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('A kanban column is read-only; use withRecords() / withMeta().');
    }
}
