<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Support;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Turns an enum into the status arrays the board consumes. Understands the mokhosh trait (`kanbanCases()`,
 * `getId()`, `getTitle()`) and Filament's own `HasLabel` / `HasColor` / `HasIcon` contracts.
 */
final class StatusSource
{
    /**
     * @param class-string<UnitEnum> $enum
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function fromEnum(string $enum): Collection
    {
        /** @var list<UnitEnum> $cases */
        $cases = method_exists($enum, 'kanbanCases') ? $enum::kanbanCases() : $enum::cases();

        /** @var array<int, array<string, mixed>> $statuses */
        $statuses = [];

        foreach ($cases as $case) {
            $value = $case instanceof BackedEnum ? $case->value : $case->name;
            $icon = $case instanceof HasIcon ? $case->getIcon() : null;

            $statuses[] = [
                'id' => method_exists($case, 'getId') ? (string) $case->getId() : (string) $value,
                'title' => self::title($case),
                'value' => $value,
                'color' => $case instanceof HasColor ? $case->getColor() : null,
                'icon' => $icon instanceof Htmlable ? null : $icon,
            ];
        }

        return collect($statuses);
    }

    public static function title(UnitEnum $case): string
    {
        if (method_exists($case, 'getTitle')) {
            return (string) $case->getTitle();
        }

        if ($case instanceof HasLabel) {
            $label = $case->getLabel();

            return $label instanceof Htmlable ? strip_tags($label->toHtml()) : (string) $label;
        }

        return (string) ($case instanceof BackedEnum ? $case->value : $case->name);
    }
}
