<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalSubdomainRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_admin_login_url_returns_to_the_public_homepage(): void
    {
        $this->get('http://localhost/admin/login')
            ->assertRedirect(route('home'));
    }

    public function test_old_admin_links_other_than_login_move_to_the_admin_subdomain(): void
    {
        $this->get('http://localhost/admin/dashboard?tab=activity')
            ->assertRedirect('https://admin.localhost/dashboard?tab=activity');
    }

    public function test_admin_login_is_available_on_the_admin_subdomain_root_path(): void
    {
        $this->get(route('admin.login'))
            ->assertOk();
    }

    public function test_client_login_is_available_on_the_member_subdomain(): void
    {
        $this->get(route('client.login'))
            ->assertOk();
    }

    public function test_old_client_login_url_moves_to_the_member_subdomain(): void
    {
        $this->get('http://localhost/client/login?from=header')
            ->assertRedirect('https://member.localhost/login?from=header');
    }

    public function test_old_portal_prefixes_on_each_subdomain_are_removed(): void
    {
        $this->get('https://admin.localhost/admin/login')
            ->assertRedirect('https://admin.localhost/login');

        $this->get('https://member.localhost/client/login')
            ->assertRedirect('https://member.localhost/login');
    }

    public function test_guests_on_each_portal_are_sent_to_the_matching_login(): void
    {
        $this->get('http://admin.localhost/')
            ->assertRedirect(route('admin.login'));

        $this->get('http://member.localhost/')
            ->assertRedirect(route('client.login'));
    }

    public function test_public_site_routes_are_not_exposed_on_the_portal_hosts(): void
    {
        $this->get('http://admin.localhost/hosting')->assertNotFound();
        $this->get('http://member.localhost/hosting')->assertNotFound();
    }

    public function test_store_browsing_lives_on_the_public_host_with_unchanged_urls(): void
    {
        foreach (['catalog.index' => '/hosting', 'catalog.vps' => '/vps', 'domain.search' => '/cek-domain',
                  'domain-premium.index' => '/domain-premium', 'license.index' => '/lisensi'] as $name => $path) {
            $url = parse_url(route($name));

            $this->assertSame(config('portal_domains.public'), $url['host'], $name);
            $this->assertSame($path, $url['path'], $name);
        }
    }

    public function test_cart_and_ordering_live_on_the_client_host_under_the_store_prefix(): void
    {
        $paths = [
            'cart.index' => '/store/keranjang',
            'cart.add-product' => '/store/keranjang/produk',
            'cart.add-addon' => '/store/keranjang/lisensi',
            'domain.add-to-cart' => '/store/keranjang/domain',
            'domains.transfer.submit' => '/store/keranjang/transfer-domain',
        ];

        foreach ($paths as $name => $path) {
            $url = parse_url(route($name));

            $this->assertSame(config('portal_domains.client'), $url['host'], $name);
            $this->assertSame($path, $url['path'], $name);
        }
    }

    public function test_store_browsing_is_open_to_guests_on_the_public_host(): void
    {
        $this->get(route('catalog.index'))->assertOk();
    }

    public function test_the_clients_own_vps_pages_do_not_collide_with_the_public_vps_catalog(): void
    {
        // /vps di portal client (VPS Saya) tetap butuh login...
        $this->get('http://member.localhost/vps')->assertRedirect(route('client.login'));

        // ...sedangkan /vps di domain publik adalah katalog VPS.
        $this->get(route('catalog.vps'))->assertOk();
    }

    public function test_cart_is_not_served_on_the_public_or_admin_hosts(): void
    {
        // Domain publik: URL yang tidak ada dikembalikan ke beranda.
        $this->get('http://localhost/store/keranjang')->assertRedirect(route('home'));
        $this->get('http://admin.localhost/store/keranjang')->assertNotFound();
    }

    public function test_old_public_cart_address_redirects_to_the_client_cart(): void
    {
        $this->get('http://localhost/keranjang')->assertStatus(301)->assertRedirect(route('cart.index'));
    }
}
