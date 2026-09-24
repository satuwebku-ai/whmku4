<?php

namespace Tests\Feature\Billing;

use App\Events\Invoice\InvoicePaid;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PaidInvoiceFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_completion_dispatches_one_paid_invoice_event(): void
    {
        Event::fake();

        $client = Client::create([
            'name' => 'Billing Test',
            'email' => 'billing-flow@example.test',
        ]);

        $invoice = Invoice::withoutEvents(fn () => Invoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'INV-TEST-0001',
            'amount' => 100,
            'tax' => 0,
            'discount' => 0,
            'issue_date' => now(),
            'due_date' => now()->addDays(7),
        ]));

        $payment = Payment::create([
            'reference' => 'PAY-TEST-0001',
            'invoice_id' => $invoice->id,
            'client_id' => $client->id,
            'amount' => 100,
            'fee' => 0,
            'total' => 100,
            'currency' => 'IDR',
        ]);

        $payment->markAsPaid('Manual');
        $payment->refresh()->markAsPaid('Manual');

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('paid', $invoice->fresh()->status);
        Event::assertDispatchedTimes(InvoicePaid::class, 1);
    }

    public function test_balance_effect_with_same_idempotency_key_is_applied_once(): void
    {
        $client = Client::create([
            'name' => 'Topup Test',
            'email' => 'topup-flow@example.test',
        ]);

        $first = $client->adjustBalance(250, 'topup', 'Test top-up', null, null, 'invoice:1:topup');
        $second = $client->adjustBalance(250, 'topup', 'Test top-up replay', null, null, 'invoice:1:topup');

        $this->assertSame($first->id, $second->id);
        $this->assertSame('250.00', (string) $client->fresh()->balance);
        $this->assertDatabaseCount('credits', 1);
    }
}