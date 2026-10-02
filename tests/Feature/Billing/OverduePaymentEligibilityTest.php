<?php

namespace Tests\Feature\Billing;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Payment\PaymentEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OverduePaymentEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(string $status, $dueDate, string $number): Invoice
    {
        $client = Client::firstOrCreate(
            ['email' => 'overdue@example.test'],
            ['name' => 'Overdue Test']
        );

        return Invoice::withoutEvents(fn () => Invoice::create([
            'client_id' => $client->id,
            'invoice_number' => $number,
            'amount' => 100,
            'tax' => 0,
            'discount' => 0,
            'status' => $status,
            'issue_date' => now()->subDays(10),
            'due_date' => $dueDate,
        ]));
    }

    public function test_invoice_due_today_and_overdue_invoice_are_payable(): void
    {
        $service = app(PaymentEligibilityService::class);

        $dueToday = $this->invoice('unpaid', now()->startOfDay(), 'INV-DUE-TODAY');
        $overdue = $this->invoice('overdue', now()->subDays(5), 'INV-OVERDUE');
        $unpaidPast = $this->invoice('unpaid', now()->subDays(2), 'INV-UNPAID-PAST');

        $this->assertTrue($service->check($dueToday)['allowed']);
        $this->assertTrue($service->check($overdue)['allowed']);
        $this->assertTrue($service->check($unpaidPast)['allowed']);
    }

    public function test_cancelled_refunded_and_paid_invoices_are_not_payable(): void
    {
        $service = app(PaymentEligibilityService::class);

        foreach (['cancelled', 'refunded', 'paid'] as $status) {
            $invoice = $this->invoice($status, now()->addDays(3), 'INV-' . strtoupper($status));

            $this->assertFalse($service->check($invoice)['allowed'], $status);
        }
    }

    public function test_late_gateway_payment_settles_an_overdue_invoice(): void
    {
        Event::fake();

        $invoice = $this->invoice('overdue', now()->subDays(2), 'INV-LATE-PAY');
        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'amount' => 100,
            'fee' => 0,
            'total' => 100,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);

        $payment->markAsPaid('Duitku');

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_payment_for_cancelled_invoice_is_closed_and_flagged_instead_of_throwing(): void
    {
        Event::fake();

        $invoice = $this->invoice('cancelled', now()->subDays(2), 'INV-CANCELLED-PAY');
        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'amount' => 100,
            'fee' => 0,
            'total' => 100,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);

        $payment->markAsPaid('Duitku');

        $payment->refresh();
        $this->assertSame('expired', $payment->status);
        $this->assertStringContainsString('refund', (string) $payment->admin_note);
        $this->assertSame('cancelled', $invoice->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['type' => 'payment', 'level' => 'danger']);
    }
}
