<?php

namespace Tests\Feature;

use App\Models\Server;
use Database\Seeders\ServerProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Server contoh tidak boleh bisa menerima data pelanggan: saat order diproses, panel
 * dihubungi dengan nama pengguna, kata sandi akun, dan domain pelanggan.
 */
class ServerProductSeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_servers_are_inactive_until_an_admin_configures_them(): void
    {
        $this->seed(ServerProductSeeder::class);

        $this->assertGreaterThan(0, Server::count());
        $this->assertSame(0, Server::where('is_active', true)->count());
    }

    public function test_seeded_control_panel_hosts_can_never_resolve(): void
    {
        $this->seed(ServerProductSeeder::class);

        foreach (Server::where('panel', 'cpanel')->get() as $server) {
            $this->assertStringEndsWith('.invalid', $server->hostname, $server->name);
            $this->assertStringEndsWith('.invalid', (string) $server->ns1, $server->name);
        }
    }

    public function test_the_seeder_uses_no_registrable_example_domains(): void
    {
        $this->assertStringNotContainsString('contoh.com', file_get_contents(base_path('database/seeders/ServerProductSeeder.php')));
    }
}
