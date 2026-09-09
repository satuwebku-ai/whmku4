<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\Invoice;

class InvoicePolicy
{
    public function view(Client $client, Invoice $invoice): bool
    {
        return $invoice->client_id === $client->id;
    }
}
