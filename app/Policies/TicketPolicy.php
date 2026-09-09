<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\Ticket;

class TicketPolicy
{
    public function view(Client $client, Ticket $ticket): bool
    {
        return $ticket->client_id === $client->id;
    }
}
