<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Concerns;

use Asignua\FilamentKanban\Support\StatusSource;
use BackedEnum;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

/**
 * Drop-in for `Mokhosh\FilamentKanban\Concerns\IsKanbanStatus`, for backed or pure enums.
 *
 * The title is the enum's Filament label when it implements `HasLabel` (otherwise the case value), and the board also
 * reads `HasColor` / `HasIcon` from the enum without any extra code here.
 *
 * @mixin BackedEnum
 */
// @phpstan-ignore trait.unused (a library trait: it is consumed by the host app's enums, never inside src)
trait IsKanbanStatus
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function statuses(): Collection
    {
        return StatusSource::fromEnum(static::class);
    }

    /**
     * Override to keep some cases off the board.
     *
     * @return list<static>
     */
    public static function kanbanCases(): array
    {
        return static::cases();
    }

    public function getId(): string
    {
        return (string) ($this instanceof BackedEnum ? $this->value : $this->name);
    }

    public function getTitle(): string
    {
        if ($this instanceof HasLabel) {
            $label = $this->getLabel();

            return $label instanceof Htmlable ? strip_tags($label->toHtml()) : (string) $label;
        }

        return $this->getId();
    }
}
