<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Support;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * The default "may this user do this to this card" rule, shared by moving and editing.
 *
 * Like a Filament resource: the model's policy decides. A model without a policy is allowed, unless the panel turned
 * on `strictAuthorization()`: then a missing policy means "no".
 */
final class KanbanGate
{
    public static function allows(Model $record, string $ability = 'update'): bool
    {
        if (Gate::getPolicyFor($record) === null) {
            return !(Filament::getCurrentOrDefaultPanel()?->isAuthorizationStrict() ?? false);
        }

        return Gate::allows($ability, $record);
    }

    /**
     * @param class-string<Model> $model
     */
    public static function allowsViewAny(string $model): bool
    {
        if (Gate::getPolicyFor($model) === null) {
            return !(Filament::getCurrentOrDefaultPanel()?->isAuthorizationStrict() ?? false);
        }

        return Gate::allows('viewAny', $model);
    }
}
