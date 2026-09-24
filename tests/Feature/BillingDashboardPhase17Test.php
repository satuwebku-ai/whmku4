<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Admin;
use App\Models\Client;
use App\Models\Invoice;

class BillingDashboardPhase17Test extends TestCase
{
    use RefreshDatabase;

    public function test_billing_dashboard_is_available_to_admin(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.billing.dashboard'));

        $response->assertOk()->assertViewIs('admin.billing.dashboard');
    }

    public function test_billing_dashboard_aggregates_paid_revenue_and_outstanding(): void
    {
        $admin = Admin::factory()->create();
        $client = Client::factory()->create();

        Invoice::factory()->create(['client_id' => $client->id, 'status' => 'paid', 'total' => 150000, 'paid_at' => now()]);
        Invoice::factory()->create(['client_id' => $client->id, 'status' => 'overdue', 'total' => 50000]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.billing.dashboard'));

        $response->assertOk()
            ->assertViewHas('revenue', fn ($value) => (float) $value === 150000.0)
            ->assertViewHas('outstanding', fn ($value) => (float) $value === 50000.0);
    }
}
