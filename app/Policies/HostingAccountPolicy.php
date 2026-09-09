<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\HostingAccount;

class HostingAccountPolicy
{
    public function view(Client $client, HostingAccount $hostingAccount): bool
    {
        return $hostingAccount->client_id === $client->id;
    }
}
