<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Transaction;
use App\Services\Billing\BillingReconciliationService;
use App\Services\Billing\TopupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingReconciliationPhase15Test extends TestCase
{
    use RefreshDatabase;

    public function test_repair_creates_missing_charge_for_paid_invoice(): void
    {
        $invoice = Invoice::factory()->create(['status' => 'paid']);
        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'status' => 'paid',
            'amount' => $invoice->total,
            'total' => $invoice->total,
        ]);

        $service = app(BillingReconciliationService::class);
        $result = $service->repair();

        $this->assertSame(1, $result['charge_repaired']);
        $this->assertDatabaseHas('transactions', [
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'type' => 'charge',
            'idempotency_key' => 'payment:' . $payment->id . ':paid',
        ]);

        $service->repair();
        $this->assertSame(1, Transaction::where('payment_id', $payment->id)->count());
    }

    /**
     * Reproduksi skenario nyata: Payment::markAsPaid() sudah membuat charge
     * transaction (atomik, sinkron), tapi job queue ProcessPaidInvoice yang
     * memanggil TopupService::applyPaidInvoice() gagal/exhausted, sehingga
     * saldo klien tidak pernah bertambah walau invoice & payment sudah paid.
     * Sebelum perbaikan, repair() mengecek tabel transactions (yang sudah
     * ada) alih-alih tabel credits (yang justru hilang), jadi kasus ini
     * tidak pernah terjaring.
     */
    public function test_repair_credits_paid_topup_whose_charge_transaction_already_exists(): void
    {
        $client = Client::factory()->create(['balance' => 0]);
        $invoice = app(TopupService::class)->createInvoice($client, 100000);
        $invoice->update(['status' => 'paid']);

        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'client_id' => $client->id,
            'status' => 'paid',
            'amount' => $invoice->total,
            'total' => $invoice->total,
        ]);

        // Simulasikan efek Payment::markAsPaid() yang sudah berjalan
        // (charge transaction ada), tapi TopupService::applyPaidInvoice()
        // (dipanggil belakangan lewat queue) belum/gagal jalan.
        Transaction::create([
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'type' => 'charge',
            'amount' => $payment->total,
            'description' => 'Pembayaran invoice ' . $invoice->invoice_number,
            'idempotency_key' => 'payment:' . $payment->id . ':paid',
        ]);

        $service = app(BillingReconciliationService::class);
        $scan = $service->scan();
        $this->assertSame(1, $scan['paid_topup_missing_credit']);

        $result = $service->repair();

        $this->assertSame(1, $result['topup_repaired']);
        $this->assertSame('100000.00', (string) $client->refresh()->balance);
        $this->assertDatabaseHas('credits', [
            'invoice_id' => $invoice->id,
            'type' => 'topup',
            'idempotency_key' => 'invoice:' . $invoice->id . ':topup',
        ]);

        $service->repair();
        $this->assertSame('100000.00', (string) $client->refresh()->balance);
    }
}