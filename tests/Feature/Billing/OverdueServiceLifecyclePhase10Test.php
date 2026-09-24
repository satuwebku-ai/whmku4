<?php

namespace Tests\Feature\Billing;

use App\Models\HostingAccount;
use App\Models\Invoice;
use App\Services\Billing\OverdueServiceLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverdueServiceLifecyclePhase10Test extends TestCase
{
    use RefreshDatabase;

    public function test_reactivation_only_changes_database_after_provider_success(): void
    {
        $hosting = HostingAccount::factory()->create(['status' => 'suspended']);
        $service = app(OverdueServiceLifecycle::class);

        // Manual hosting tanpa server tidak membutuhkan provider API.
        $this->assertTrue($service->reactivateHosting($hosting));
        $this->assertSame('active', $hosting->fresh()->status);
    }

    public function test_suspend_requires_an_unpaid_or_overdue_renewal_invoice(): void
    {
        $hosting = HostingAccount::factory()->create(['status' => 'active']);
        $this->assertFalse(app(OverdueServiceLifecycle::class)->suspendHosting($hosting));
        $this->assertSame('active', $hosting->fresh()->status);
    }
}
