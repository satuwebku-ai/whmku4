<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
{
    public function test_canonical_order_status_enum_is_used(): void
    {
        $order = new Order(['status' => OrderStatus::PendingPayment]);

        $this->assertSame(OrderStatus::PendingPayment, $order->status);
    }

    public function test_order_cannot_jump_from_pending_payment_to_completed(): void
    {
        $this->expectException(ValidationException::class);

        $order = new Order(['order_number' => 'ORD-TEST', 'status' => OrderStatus::PendingPayment]);
        $order->transitionTo(OrderStatus::Completed);
    }
}
