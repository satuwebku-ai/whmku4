<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\Payment;

class PaymentPolicy
{
    public function view(Client $client, Payment $payment): bool
    {
        return $payment->client_id === $client->id;
    }
}
