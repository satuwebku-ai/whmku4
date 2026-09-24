<?php

namespace Tests\Feature\Billing;

use App\Jobs\Billing\ProcessPaidInvoice;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\InvoiceItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EndToEndPhase20Test extends TestCase
{
    use RefreshDatabase;

    public function test_paid_checkout_reaches_paid_invoice_transaction_and_fulfillment_queue(): void
    {
        Queue::fake();

        $client = Client::create([
            'name' => 'E2E Client',
            'email' => 'e2e@example.test',
        ]);

        $order = Order::create([
            'client_id' => $client->id,
            'product_name' => 'E2E Hosting',
            'order_type' => 'hosting',
            'amount' => 150,
            'status' => 'pending',
        ]);
        $order->markPendingPayment('E2E checkout siap dibayar.');

        $invoice = Invoice::withoutEvents(fn () => Invoice::create([
            'client_id' => $client->id,
            'order_id' => $order->id,
            'invoice_number' => 'INV-E2E-0001',
            'amount' => 150,
            'tax' => 0,
            'discount' => 0,
            'total' => 150,
            'issue_date' => now(),
            'due_date' => now()->addDays(7),
        ]));

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'order_id' => $order->id,
            'description' => 'E2E Hosting',
            'amount' => 150,
        ]);

        $payment = Payment::create([
            'reference' => 'PAY-E2E-0001',
            'invoice_id' => $invoice->id,
            'client_id' => $client->id,
            'amount' => 150,
            'fee' => 0,
            'total' => 150,
            'currency' => 'IDR',
        ]);

        $payment->markAsPaid('E2E-Gateway');

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('paid', $order->fresh()->status->value);

        $this->assertDatabaseHas('transactions', [
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'type' => 'charge',
            'amount' => '150.00',
            'idempotency_key' => 'payment:' . $payment->id . ':paid',
        ]);

        Queue::assertPushed(ProcessPaidInvoice::class, function (ProcessPaidInvoice $job) use ($invoice) {
            return $job->invoiceId === $invoice->id;
        });
    }

    public function test_payment_replay_does_not_duplicate_financial_ledger_or_fulfillment_job(): void
    {
        Queue::fake();

        $client = Client::create([
            'name' => 'E2E Replay Client',
            'email' => 'e2e-replay@example.test',
        ]);

        $invoice = Invoice::withoutEvents(fn () => Invoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'INV-E2E-REPLAY-0001',
            'amount' => 200,
            'tax' => 0,
            'discount' => 0,
            'total' => 200,
            'issue_date' => now(),
            'due_date' => now()->addDays(7),
        ]));

        $payment = Payment::create([
            'reference' => 'PAY-E2E-REPLAY-0001',
            'invoice_id' => $invoice->id,
            'client_id' => $client->id,
            'amount' => 200,
            'fee' => 0,
            'total' => 200,
            'currency' => 'IDR',
        ]);

        $payment->markAsPaid('E2E-Gateway');
        $payment->refresh()->markAsPaid('E2E-Gateway');

        $this->assertDatabaseCount('transactions', 1);
        Queue::assertPushed(ProcessPaidInvoice::class, 1);
    }
}
