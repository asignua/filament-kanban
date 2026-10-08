<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class MakeKanbanBoardCommand extends GeneratorCommand
{
    protected $name = 'make:kanban';

    protected $description = 'Create a Filament Kanban board page';

    protected $type = 'Kanban page';

    protected function getStub(): string
    {
        return __DIR__.'/../../stubs/board.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Filament\Pages';
    }

    protected function buildClass($name): string
    {
        $class = class_basename($name);
        $base = Str::of($class)->replaceMatches('/(Kanban)?Board$/', '')->toString();
        $model = $this->option('model');
        $enum = $this->option('enum');

        $modelClass = is_string($model) && $model !== '' ? ltrim($model, '\\') : $this->rootNamespace().'Models\\'.Str::singular($base !== '' ? $base : 'Task');
        $enumClass = is_string($enum) && $enum !== '' ? ltrim($enum, '\\') : $this->rootNamespace().'Enums\\'.class_basename($modelClass).'Status';

        return str_replace(
            ['{{ modelClass }}', '{{ modelBase }}', '{{ enumClass }}', '{{ enumBase }}', '{{ label }}'],
            [$modelClass, class_basename($modelClass), $enumClass, class_basename($enumClass), Str::headline($base !== '' ? $base : $class)],
            parent::buildClass($name),
        );
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions(): array
    {
        return [
            ['model', 'm', InputOption::VALUE_REQUIRED, 'The model whose records are the cards (default: App\\Models\\<Name>)'],
            ['enum', 'e', InputOption::VALUE_REQUIRED, 'The status enum that defines the columns (default: App\\Enums\\<Model>Status)'],
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the page already exists'],
        ];
    }
}
