<?php

namespace Tests\Feature\Billing;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Tax;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceBillingPhase4Test extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_total_is_centralized_and_discount_is_persisted(): void
    {
        $client = Client::create([
            'name' => 'Invoice Test',
            'email' => 'invoice-test@example.test',
        ]);

        $invoice = Invoice::create([
            'client_id' => $client->id,
            'amount' => '100.00',
            'tax' => '11.00',
            'discount' => '10.00',
            'issue_date' => now(),
            'due_date' => now()->addDays(3),
        ]);

        $this->assertSame('10.00', (string) $invoice->discount);
        $this->assertSame('101.00', (string) $invoice->total);
    }

    public function test_active_tax_can_be_calculated_without_floating_point_total(): void
    {
        $tax = Tax::create([
            'name' => 'PPN',
            'rate_percentage' => '11.00',
            'country' => 'ID',
            'is_active' => true,
        ]);

        $service = app(\App\Services\Billing\InvoiceCalculationService::class);
        $this->assertSame('11.00', $service->calculateTax('100.00', $tax));
        $this->assertSame('101.00', $service->total('100.00', '11.00', '10.00'));
    }

    public function test_unpaid_invoice_becomes_overdue_only_after_due_date(): void
    {
        $client = Client::create([
            'name' => 'Overdue Test',
            'email' => 'overdue-test@example.test',
        ]);

        $invoice = Invoice::create([
            'client_id' => $client->id,
            'amount' => 100,
            'issue_date' => now()->subDays(4),
            'due_date' => now()->subDay(),
        ]);

        $this->assertTrue($invoice->markOverdue());
        $this->assertSame('overdue', $invoice->fresh()->status);
    }
}
