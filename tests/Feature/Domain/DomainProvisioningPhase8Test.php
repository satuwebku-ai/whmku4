<?php

namespace Tests\Feature\Domain;

use App\Models\Client;
use App\Models\Domain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DomainProvisioningPhase8Test extends TestCase
{
    use RefreshDatabase;

    public function test_domain_provisioning_tracking_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('domains', [
            'provisioning_started_at',
            'provisioning_finished_at',
            'provisioning_attempts',
            'provisioning_key',
        ]));
    }

    public function test_domain_provisioning_marker_is_idempotent_for_the_same_key(): void
    {
        $client = Client::create([
            'name' => 'Domain Provisioning Test',
            'email' => 'domain-provisioning@example.test',
        ]);

        $domain = Domain::create([
            'client_id' => $client->id,
            'domain_name' => 'example.test',
            'status' => 'pending',
            'provision_status' => 'manual',
        ]);

        $domain->markProvisioning();
        $key = $domain->fresh()->provisioning_key;

        $domain->markProvisioning();
        $domain->refresh();

        $this->assertSame(2, $domain->provisioning_attempts);
        $this->assertSame($key, $domain->provisioning_key);
        $this->assertSame('provisioning', $domain->provision_status);
        $this->assertNotNull($domain->provisioning_started_at);
        $this->assertNull($domain->provisioning_finished_at);

        $domain->markProvisioningFinished('registered', 'Domain berhasil didaftarkan.');
        $this->assertSame('registered', $domain->fresh()->provision_status);
        $this->assertNotNull($domain->fresh()->provisioning_finished_at);
    }
}
