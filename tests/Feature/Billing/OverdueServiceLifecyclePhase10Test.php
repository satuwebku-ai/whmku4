<?php

namespace Tests\Feature\Billing;

use App\Models\HostingAccount;
use App\Models\Invoice;
use App\Models\Setting;
use App\Services\Billing\OverdueServiceLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
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

    public function test_legacy_forty_four_day_grace_setting_is_capped_at_thirty_days(): void
    {
        Setting::put('auto_suspend_enabled', '1');
        Setting::put('suspend_grace_days', '44');

        $hosting = HostingAccount::factory()->active()->create();
        $invoice = Invoice::factory()->create([
            'client_id' => $hosting->client_id,
            'status' => 'overdue',
            'due_date' => today()->subDays(31)->toDateString(),
        ]);
        $hosting->update(['renewal_invoice_id' => $invoice->id]);

        $exitCode = Artisan::call('lumora:suspend-overdue');

        $this->assertSame(0, $exitCode);
        $this->assertSame('suspended', $hosting->fresh()->status);
    }
}
