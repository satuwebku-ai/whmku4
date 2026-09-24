<?php

namespace Tests\Feature\Billing;

use App\Models\Client;
use App\Models\Invoice;
use App\Services\Billing\CreditService;
use App\Services\Billing\TopupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditTopupPhase12Test extends TestCase
{
    use RefreshDatabase;

    public function test_paid_topup_is_credited_only_once(): void
    {
        $client = Client::factory()->create(['balance' => 0]);
        $invoice = app(TopupService::class)->createInvoice($client, 100000);
        $invoice->update(['status' => 'paid']);

        $service = app(TopupService::class);
        $service->applyPaidInvoice($invoice->refresh());
        $service->applyPaidInvoice($invoice->refresh());

        $this->assertSame('100000.00', (string) $client->refresh()->balance);
        $this->assertSame(1, $client->credits()->where('idempotency_key', 'invoice:' . $invoice->id . ':topup')->count());
    }

    public function test_debit_uses_idempotency_key(): void
    {
        $client = Client::factory()->create(['balance' => 150000]);
        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'amount' => 50000,
            'tax' => 0,
            'discount' => 0,
            'total' => 50000,
            'status' => 'unpaid',
        ]);

        $credits = app(CreditService::class);
        $credits->debit($client, 50000, 'Test payment', 'payment', $invoice, null, 'payment:test:1');
        $credits->debit($client, 50000, 'Replay payment', 'payment', $invoice, null, 'payment:test:1');

        $this->assertSame('100000.00', (string) $client->refresh()->balance);
        $this->assertSame(1, $client->credits()->where('idempotency_key', 'payment:test:1')->count());
    }
}
