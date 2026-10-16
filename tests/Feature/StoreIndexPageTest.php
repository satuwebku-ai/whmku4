<?php

namespace Tests\Feature;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Halaman /store di portal client: pintasan ke semua menu jualan dan keranjang. */
class StoreIndexPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_url_is_under_store_on_the_client_domain(): void
    {
        $this->assertSame('/store', parse_url(route('store.index'), PHP_URL_PATH));
        $this->assertSame(config('portal_domains.client'), parse_url(route('store.index'), PHP_URL_HOST));
    }

    public function test_logged_in_client_sees_every_selling_menu_and_the_cart(): void
    {
        $this->actingAs(Client::factory()->create(), 'client');

        $this->get(route('store.index'))
            ->assertOk()
            ->assertSee('store-topbar', false)
            ->assertDontSee('id="clientTopbar"', false)
            // Klien login memakai halaman Store miliknya, bukan etalase publik.
            ->assertSee(route('store.hosting'), false)
            ->assertSee(route('store.vps'), false)
            ->assertSee(route('store.domain'), false)
            ->assertSee(route('store.license'), false)
            ->assertSee(route('cart.index'), false)
            ->assertSee('Pesan Hosting')
            ->assertSee('Pesan VPS')
            ->assertSee('Cek &amp; Daftar Domain', false)
            ->assertSee('Beli Lisensi')
            ->assertSee('Keranjang');
    }

    public function test_guests_can_open_the_store_page(): void
    {
        $this->get(route('store.index'))
            ->assertOk()
            ->assertSee('Pesan Hosting')
            // Tamu tetap diarahkan ke etalase publik.
            ->assertSee(route('catalog.index'), false)
            ->assertDontSee(route('store.hosting'), false);
    }

    public function test_the_store_link_in_the_header_is_marked_active(): void
    {
        $this->actingAs(Client::factory()->create(), 'client');

        $this->get(route('store.index'))
            ->assertOk()
            ->assertSee('store-topbar__link is-active', false);
    }

    public function test_the_dashboard_sidebar_links_to_the_store_instead_of_the_four_loose_entries(): void
    {
        $this->actingAs(Client::factory()->create(), 'client');

        $this->get(route('client.dashboard'))
            ->assertOk()
            ->assertSee(route('store.index'), false)
            ->assertDontSee('Pesan Hosting')
            ->assertDontSee('Beli Lisensi');
    }
}
