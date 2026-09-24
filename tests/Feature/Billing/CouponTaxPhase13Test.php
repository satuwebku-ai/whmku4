<?php

namespace Tests\Feature\Billing;

use App\Models\Client;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\Tax;
use App\Services\Billing\CouponService;
use App\Services\Billing\TaxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponTaxPhase13Test extends TestCase
{
    use RefreshDatabase;

    public function test_coupon_reservation_is_idempotent_per_invoice(): void
    {
        $client = Client::factory()->create();
        $coupon = Coupon::create([
            'code' => 'SAVE10',
            'type' => 'percent',
            'value' => 10,
            'applies_to' => 'all',
            'usage_limit' => 10,
            'usage_limit_per_client' => 1,
            'is_active' => true,
        ]);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'amount' => 100000, 'discount' => 10000]);

        $service = app(CouponService::class);
        $first = $service->reserveForInvoice($coupon, $client, $invoice, 10000);
        $second = $service->reserveForInvoice($coupon, $client, $invoice, 10000);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('coupon_usages', 1);
    }

    public function test_tax_is_snapshotted_and_calculated_on_net_amount(): void
    {
        $tax = Tax::create([
            'name' => 'PPN Indonesia',
            'rate_percentage' => 11,
            'country' => 'ID',
            'is_active' => true,
        ]);
        $invoice = Invoice::factory()->create([
            'amount' => 100000,
            'discount' => 10000,
            'tax' => 0,
            'total' => 90000,
        ]);

        app(TaxService::class)->applyToInvoice($invoice, $tax);
        $invoice->save();
        $invoice->refresh();

        $this->assertSame($tax->id, $invoice->tax_id);
        $this->assertSame('11.00', (string) $invoice->tax_rate);
        $this->assertSame('9,900.00', number_format((float) $invoice->tax, 2));
        $this->assertSame('99,900.00', number_format((float) $invoice->total, 2));
    }

    public function test_inactive_tax_cannot_be_used(): void
    {
        $tax = Tax::create([
            'name' => 'Inactive',
            'rate_percentage' => 11,
            'country' => 'ID',
            'is_active' => false,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(TaxService::class)->assertUsable($tax->id);
    }
}
