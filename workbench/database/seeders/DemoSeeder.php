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
            'todo' => ['Write the onboarding guide', 'Design the invoice template', 'Pick a licence for the SDK', 'Plan the Q4 roadmap', 'Audit third-party scripts'],
            'doing' => ['Migrate the billing webhooks', 'Review the extension points', 'Speed up the dashboard queries'],
            'done' => ['Scaffold the plugin', 'Set up CI', 'Publish the first beta', 'Fix the timezone bug'],
        ];

        foreach ($titles as $status => $list) {
            foreach ($list as $index => $title) {
                Task::query()->create(['title' => $title, 'status' => $status, 'position' => $index + 1, 'locked' => $title === 'Pick a licence for the SDK']);
            }
        }

        foreach (['new' => ['Printer is on fire', 'Cannot log in'], 'open' => ['Slow dashboard'], 'closed' => ['Typo on the pricing page']] as $status => $list) {
            foreach ($list as $index => $title) {
                Ticket::query()->create(['title' => $title, 'status' => $status, 'sort' => $index + 1]);
            }
        }
    }
}
