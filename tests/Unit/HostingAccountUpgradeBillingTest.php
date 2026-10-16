<?php

namespace Tests\Unit;

use App\Models\HostingAccount;
use App\Models\Product;
use Carbon\Carbon;
use Tests\TestCase;

class HostingAccountUpgradeBillingTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_prorated_upgrade_uses_custom_cycle_length_and_client_price_snapshot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-07 12:00:00'));

        $currentProduct = Product::make(['custom_cycle_days' => 60]);
        $targetProduct = Product::make(['price_custom' => 20000]);
        $hosting = HostingAccount::make([
            'billing_cycle' => 'custom',
            'price' => 10000,
            'next_due_date' => now()->addDays(30),
        ]);
        $hosting->setRelation('product', $currentProduct);

        $this->assertSame(20000.0, $hosting->priceForProductCycle($targetProduct));
        $this->assertSame(5000.0, $hosting->prorateUpgrade($targetProduct, 20000));
    }

    public function test_late_monthly_renewal_starts_a_new_cycle_instead_of_remaining_overdue(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-07 12:00:00'));

        $hosting = HostingAccount::make([
            'billing_cycle' => 'monthly',
            'next_due_date' => '2026-08-24',
        ]);

        $nextDueDate = $hosting->nextCycleDate(Carbon::parse('2026-10-07 12:00:00'));

        $this->assertSame('2026-11-07', $nextDueDate->toDateString());
    }
}
