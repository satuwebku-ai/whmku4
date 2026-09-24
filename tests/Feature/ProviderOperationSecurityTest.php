<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProviderOperationSecurityTest extends TestCase
{
    public function test_vps_mutating_actions_are_serialized_per_resource(): void
    {
        $source = file_get_contents(
            app_path('Http/Controllers/Client/VpsController.php')
        );

        $this->assertGreaterThanOrEqual(
            4,
            substr_count($source, 'Cache::lock("vps-operation:{$vps->id}", 180)')
        );
    }

    public function test_domain_provider_toggles_share_a_resource_lock(): void
    {
        $source = file_get_contents(
            app_path('Http/Controllers/Client/ServiceController.php')
        );

        $this->assertStringContainsString(
            'private function withDomainProviderLock(Domain $domain, callable $operation): array',
            $source
        );
        $this->assertGreaterThanOrEqual(
            3,
            substr_count($source, '$this->withDomainProviderLock($domain')
        );
        $this->assertStringContainsString(
            'Domain::query()->lockForUpdate()->findOrFail($domain->id)',
            $source
        );
    }

    public function test_client_check_then_create_and_cancel_flows_are_locked(): void
    {
        $source = file_get_contents(
            app_path('Http/Controllers/Client/ServiceController.php')
        );

        $this->assertGreaterThanOrEqual(
            5,
            substr_count($source, 'lockForUpdate()->findOrFail')
        );
        $this->assertStringContainsString(
            'domain-document-upload:{$domain->id}',
            $source
        );
        $this->assertStringContainsString(
            'hosting-addon-operation:{$addon->id}',
            $source
        );
    }

    public function test_paid_provider_followups_are_serialized_per_invoice(): void
    {
        $source = file_get_contents(
            app_path('Services/Provisioning/ProvisioningService.php')
        );

        foreach ([
            'process-privacy-payment:',
            'process-addon-payment:',
            'process-upgrade-payment:',
            'process-renewal-payment:',
        ] as $lockKey) {
            $this->assertStringContainsString($lockKey, $source);
            $this->assertStringContainsString('->block(5', $source);
        }
    }
}