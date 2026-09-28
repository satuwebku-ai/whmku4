<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Client;
use App\Models\Domain;
use App\Models\DomainInterest;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainInterestAndCheckoutCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_interest_is_only_recorded_for_authenticated_clients(): void
    {
        DomainInterest::recordSearch(['Anonim.com']);

        $this->assertDatabaseCount('domain_interests', 0);

        $client = Client::factory()->create();
        $this->actingAs($client, 'client');

        DomainInterest::recordSearch(['Contoh.COM', 'contoh.com'], ['query' => 'contoh']);
        DomainInterest::recordCart('Contoh.COM', null, null, 1);

        $this->assertDatabaseCount('domain_interests', 2);
        $this->assertDatabaseHas('domain_interests', [
            'client_id' => $client->id,
            'event_type' => DomainInterest::SEARCH,
            'domain_name' => 'contoh.com',
        ]);
        $this->assertDatabaseHas('domain_interests', [
            'client_id' => $client->id,
            'event_type' => DomainInterest::CART,
            'domain_name' => 'contoh.com',
        ]);
    }

    public function test_overdue_checkout_expires_order_cancels_pending_domain_and_invoice(): void
    {
        $client = Client::factory()->create();
        $order = Order::create([
            'client_id' => $client->id,
            'product_name' => 'Hosting Test',
            'order_type' => 'hosting',
            'amount' => 100000,
            'status' => OrderStatus::PendingPayment,
        ]);
        $domain = Domain::create([
            'client_id' => $client->id,
            'order_id' => $order->id,
            'domain_name' => 'checkout-terlambat.test',
            'status' => 'pending',
            'provision_status' => 'manual',
        ]);
        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'status' => 'overdue',
            'due_date' => now()->subDays(5),
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'order_id' => $order->id,
            'description' => 'Hosting Test',
            'amount' => 100000,
        ]);
        Setting::put('checkout_cancel_grace_days', 3, 'cron');

        $this->artisan('lumora:cancel-overdue-checkouts')
            ->assertExitCode(0);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Expired->value,
        ]);
        $this->assertDatabaseHas('domains', [
            'id' => $domain->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_overdue_invoice_without_checkout_order_is_not_cancelled(): void
    {
        $invoice = Invoice::factory()->create([
            'status' => 'overdue',
            'due_date' => now()->subDays(10),
        ]);
        Setting::put('checkout_cancel_grace_days', 3, 'cron');

        $this->artisan('lumora:cancel-overdue-checkouts')
            ->assertExitCode(0);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'overdue',
        ]);
    }
}