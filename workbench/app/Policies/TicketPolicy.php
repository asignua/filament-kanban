<?php

declare(strict_types=1);

namespace Workbench\App\Policies;

use Workbench\App\Models\Ticket;
use Workbench\App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $ticket->title !== 'Forbidden';
    }
}
