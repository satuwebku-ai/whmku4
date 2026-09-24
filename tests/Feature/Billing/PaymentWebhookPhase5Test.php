<?php

namespace Tests\Feature\Billing;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PaymentWebhookPhase5Test extends TestCase
{
    use RefreshDatabase;

    public function test_paid_payment_creates_one_idempotent_charge_transaction(): void
    {
        Event::fake();

        $client = Client::create([
            'name' => 'Webhook Test',
            'email' => 'webhook@example.test',
        ]);

        $invoice = Invoice::withoutEvents(fn () => Invoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'INV-WEBHOOK-0001',
            'amount' => 125,
            'tax' => 0,
            'discount' => 0,
            'issue_date' => now(),
            'due_date' => now()->addDays(7),
        ]));

        $payment = Payment::create([
            'reference' => 'PAY-WEBHOOK-0001',
            'invoice_id' => $invoice->id,
            'client_id' => $client->id,
            'amount' => 125,
            'fee' => 5,
            'total' => 130,
            'currency' => 'IDR',
        ]);

        $payment->markAsPaid('Midtrans');
        $payment->refresh()->markAsPaid('Midtrans');

        $this->assertDatabaseHas('transactions', [
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'type' => 'charge',
            'amount' => '130.00',
            'idempotency_key' => 'payment:' . $payment->id . ':paid',
        ]);

        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_midtrans_webhook_rejects_amount_mismatch_before_payment_completion(): void
    {
        $client = Client::create([
            'name' => 'Midtrans Test',
            'email' => 'midtrans@example.test',
        ]);

        $invoice = Invoice::withoutEvents(fn () => Invoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'INV-MIDTRANS-0001',
            'amount' => 100,
            'tax' => 0,
            'discount' => 0,
            'issue_date' => now(),
            'due_date' => now()->addDays(7),
        ]));

        $gateway = PaymentGateway::create([
            'name' => 'Midtrans Test',
            'driver' => 'midtrans',
            'server_key' => 'server-key-test',
            'currency' => 'IDR',
            'is_active' => true,
        ]);

        $payment = Payment::create([
            'reference' => 'PAY-MIDTRANS-0001',
            'invoice_id' => $invoice->id,
            'client_id' => $client->id,
            'payment_gateway_id' => $gateway->id,
            'amount' => 100,
            'fee' => 0,
            'total' => 100,
            'currency' => 'IDR',
        ]);

        $payload = [
            'order_id' => $payment->reference,
            'status_code' => '200',
            'gross_amount' => '999.00',
            'transaction_status' => 'settlement',
            'signature_key' => hash('sha512', $payment->reference . '200999.00server-key-test'),
        ];

        $response = $this->postJson('/payment/webhook/midtrans', $payload);

        $response->assertStatus(400);
        $this->assertSame('initiated', $payment->fresh()->status);
        $this->assertSame('unpaid', $invoice->fresh()->status);
    }
}
