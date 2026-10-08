<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Tests\Feature;

use Asignua\FilamentKanban\KanbanPlugin;
use Asignua\FilamentKanban\KanbanServiceProvider;
use Asignua\FilamentKanban\Tests\TestCase;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentAsset;

class SmokeTest extends TestCase
{
    public function test_the_panel_boots(): void
    {
        $this->assertSame('admin', Filament::getCurrentPanel()?->getId());
    }

    public function test_the_plugin_is_registered_on_the_panel(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertTrue($panel->hasPlugin('asignua-filament-kanban'));
        $this->assertInstanceOf(KanbanPlugin::class, $panel->getPlugin('asignua-filament-kanban'));
    }

    public function test_the_translations_are_loaded(): void
    {
        $this->assertSame('Save', __('filament-kanban::filament-kanban.edit_save'));
    }

    public function test_the_assets_are_registered(): void
    {
        $this->assertNotSame('', FilamentAsset::getStyleHref(KanbanServiceProvider::STYLESHEET, KanbanServiceProvider::PACKAGE));
        $this->assertNotSame('', FilamentAsset::getAlpineComponentSrc(KanbanServiceProvider::COMPONENT, KanbanServiceProvider::PACKAGE));
    }

    public function test_the_shipped_assets_exist(): void
    {
        $this->assertFileExists(__DIR__.'/../../resources/dist/filament-kanban.css');
        $this->assertFileExists(__DIR__.'/../../resources/dist/kanban-board.js');
    }
}
