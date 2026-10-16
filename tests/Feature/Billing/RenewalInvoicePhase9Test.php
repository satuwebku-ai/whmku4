<?php

namespace Tests\Feature\Billing;

use App\Models\Client;
use App\Models\Domain;
use App\Models\HostingAccount;
use App\Models\InvoiceItem;
use App\Services\Billing\RenewalInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenewalInvoicePhase9Test extends TestCase
{
    use RefreshDatabase;

    public function test_hosting_renewal_invoice_is_idempotent_and_itemized(): void
    {
        $client = Client::create([
            'name' => 'Renewal Hosting Test',
            'email' => 'renewal-hosting@example.test',
        ]);

        $hosting = HostingAccount::create([
            'client_id' => $client->id,
            'domain' => 'renewal.example.test',
            'package' => 'Pro',
            'price' => 100000,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'next_due_date' => now()->addDays(7)->toDateString(),
        ]);

        $service = app(RenewalInvoiceService::class);
        $first = $service->createHostingInvoice($hosting);
        $second = $service->createHostingInvoice($hosting->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->id, $hosting->fresh()->renewal_invoice_id);
        $this->assertSame(1, InvoiceItem::where('invoice_id', $first->id)->count());
        $this->assertSame('100000.00', $first->fresh()->amount);
        $this->assertSame('100000.00', $first->fresh()->total);
    }

    public function test_domain_renewal_invoice_is_idempotent_inside_renewal_window(): void
    {
        $client = Client::create([
            'name' => 'Renewal Domain Test',
            'email' => 'renewal-domain@example.test',
        ]);

        $domain = Domain::create([
            'client_id' => $client->id,
            'domain_name' => 'renewal.example.com',
            'price' => 125000,
            'years' => 1,
            'status' => 'active',
            'expiry_date' => now()->addDays(10)->toDateString(),
            'auto_renew' => true,
        ]);

        $service = app(RenewalInvoiceService::class);
        $first = $service->createDomainInvoice($domain);
        $second = $service->createDomainInvoice($domain->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->id, $domain->fresh()->renewal_invoice_id);
        $this->assertSame(1, InvoiceItem::where('invoice_id', $first->id)->count());
        $this->assertSame('125000.00', $first->fresh()->amount);
        $this->assertSame('125000.00', $first->fresh()->total);
    }

    public function test_late_hosting_catch_up_invoice_is_not_created_with_a_backdated_due_date(): void
    {
        $client = Client::create([
            'name' => 'Late Renewal Test',
            'email' => 'late-renewal@example.test',
        ]);

        $hosting = HostingAccount::create([
            'client_id' => $client->id,
            'domain' => 'late-renewal.example.test',
            'package' => 'Pro',
            'price' => 100000,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'next_due_date' => today()->subDays(44)->toDateString(),
        ]);

        $invoice = app(RenewalInvoiceService::class)->createHostingInvoice($hosting);

        $this->assertSame(today()->toDateString(), $invoice->fresh()->due_date->toDateString());
    }
}
