<?php

declare(strict_types=1);

namespace Asignua\FilamentKanban\Tests\Feature;

use Asignua\FilamentKanban\Tests\TestCase;
use Illuminate\Support\Facades\File;

class CommandsTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = app_path('Filament/Pages/UsersKanbanBoard.php');
        File::delete($this->path);
    }

    protected function tearDown(): void
    {
        File::delete($this->path);

        parent::tearDown();
    }

    public function test_make_kanban_generates_a_board_page(): void
    {
        $this->artisan('make:kanban', ['name' => 'UsersKanbanBoard'])->assertSuccessful();

        $this->assertFileExists($this->path);

        $code = File::get($this->path);

        $this->assertStringContainsString('class UsersKanbanBoard extends KanbanBoard', $code);
        $this->assertStringContainsString('protected static string $model = User::class;', $code);
        $this->assertStringContainsString('protected static string $statusEnum = UserStatus::class;', $code);
        $this->assertStringContainsString('use Asignua\FilamentKanban\Pages\KanbanBoard;', $code);
        $this->assertStringNotContainsString('{{', $code);
    }

    public function test_the_model_and_enum_can_be_named(): void
    {
        $this->artisan('make:kanban', ['name' => 'UsersKanbanBoard', '--model' => 'App\\Domain\\Person', '--enum' => 'App\\Domain\\Mood'])->assertSuccessful();

        $code = File::get($this->path);

        $this->assertStringContainsString('use App\Domain\Person;', $code);
        $this->assertStringContainsString('protected static string $statusEnum = Mood::class;', $code);
    }

    public function test_the_generated_page_is_valid_php(): void
    {
        $this->artisan('make:kanban', ['name' => 'UsersKanbanBoard'])->assertSuccessful();

        $tokens = token_get_all(File::get($this->path), TOKEN_PARSE);

        $this->assertNotEmpty($tokens);
    }

    public function test_the_install_command_is_registered_under_the_mokhosh_name(): void
    {
        $this->assertArrayHasKey('filament-kanban:install', \Illuminate\Support\Facades\Artisan::all());
    }
}
