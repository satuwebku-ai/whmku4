<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\ServerGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerGroupProvisioningFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_group_only_assigns_cpanel_members_for_automatic_provisioning(): void
    {
        $group = $this->group();
        $cpanel = $this->server('cpanel');
        $directAdmin = $this->server('directadmin');
        $cloud = $this->server('vps', 'idcloudhost');

        $group->servers()->attach([
            $cpanel->id => ['priority' => 10, 'is_active' => true],
            $directAdmin->id => ['priority' => 1, 'is_active' => true],
            $cloud->id => ['priority' => 2, 'is_active' => true],
        ]);

        $this->assertSame($cpanel->id, $group->pickServer()?->id);
    }

    public function test_group_without_a_ready_cpanel_member_returns_no_server_for_manual_fulfillment(): void
    {
        $group = $this->group();
        $directAdmin = $this->server('directadmin');
        $cloud = $this->server('vps', 'idcloudhost');

        $group->servers()->attach([
            $directAdmin->id => ['priority' => 1, 'is_active' => true],
            $cloud->id => ['priority' => 2, 'is_active' => true],
        ]);

        $this->assertNull($group->pickServer());
    }

    private function group(): ServerGroup
    {
        return ServerGroup::create([
            'name' => 'Hosting Group',
            'slug' => 'hosting-group',
            'selection_mode' => 'priority',
            'is_active' => true,
        ]);
    }

    private function server(string $panel, ?string $provider = null): Server
    {
        return Server::create([
            'name' => strtoupper($panel) . ' server',
            'panel' => $panel,
            'vps_provider' => $provider,
            'hostname' => $panel === 'vps' ? '' : $panel . '.example.test',
            'port' => $panel === 'vps' ? null : 2087,
            'api_username' => $panel === 'vps' ? '' : 'root',
            'api_token' => 'test-token',
            'is_active' => true,
            'is_maintenance' => false,
        ]);
    }
}
