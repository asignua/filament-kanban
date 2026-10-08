<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Workbench\App\Models\Task;
use Workbench\App\Models\Ticket;
use Workbench\App\Models\User;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(['email' => 'emma@example.com'], ['name' => 'Emma', 'password' => 'password', 'is_active' => true]);

        $titles = [
            'todo' => ['Write the README', 'Draw the cover', 'Pick a licence'],
            'doing' => ['Port the reorder algorithm', 'Review the extension points'],
            'done' => ['Scaffold the plugin'],
        ];

        foreach ($titles as $status => $list) {
            foreach ($list as $index => $title) {
                Task::query()->create(['title' => $title, 'status' => $status, 'position' => $index + 1, 'locked' => $title === 'Pick a licence']);
            }
        }

        foreach (['new' => ['Printer is on fire', 'Cannot log in'], 'open' => ['Slow dashboard'], 'closed' => ['Typo on the pricing page']] as $status => $list) {
            foreach ($list as $index => $title) {
                Ticket::query()->create(['title' => $title, 'status' => $status, 'sort' => $index + 1]);
            }
        }
    }
}
