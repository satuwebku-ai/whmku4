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
        // $order->status di-cast ke enum OrderStatus (bukan string
        // biasa) -- in_array(..., true) dengan daftar string tidak akan
        // pernah cocok (enum !== string secara ketat), yang sebelumnya
        // membuat cancel() SELALU false berapa pun status order-nya.
        return $this->view($user, $order)
            && in_array($order->status->value, ['draft', 'pending_payment', 'pending', 'requirements_pending'], true);
    }
}