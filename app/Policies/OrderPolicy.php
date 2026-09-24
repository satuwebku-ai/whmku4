<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\Client;

class OrderPolicy
{
    public function view(?Client $user, Order $order): bool
    {
        return (int) auth('client')->id() === (int) $order->client_id
            || (int) auth('admin')->id() > 0;
    }

    public function cancel(?Client $user, Order $order): bool
    {
        return $this->view($user, $order)
            && in_array($order->status, ['draft', 'pending_payment', 'pending', 'requirements_pending'], true);
    }
}