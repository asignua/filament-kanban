<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Commands;

use Illuminate\Console\Command;

/**
 * Same name as mokhosh/filament-kanban's, so existing deploy scripts keep working: it publishes the Filament assets.
 */
class InstallCommand extends Command
{
    protected $signature = 'filament-kanban:install';

    protected $description = 'Publish the Filament assets (styles and the board script) used by filament-kanban';

    public function handle(): int
    {
        return $this->call('filament:assets');
    }
}
