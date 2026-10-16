<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductType;
use App\Support\StoreUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman jualan di dalam Store (/store/hosting, /store/vps, /store/domain, ...):
 * untuk klien login, tampil di layout toko portal member. Etalase publik tetap
 * terpisah untuk tamu.
 */
class StoreCatalogPagesTest extends TestCase
{
    use RefreshDatabase;

    private function login(): Client
    {
        $client = Client::factory()->create();
        $this->actingAs($client, 'client');

        return $client;
    }

    private function product(): array
    {
        $type = ProductType::where('slug', 'hosting')->first()
            ?? ProductType::create(['slug' => 'hosting', 'name' => 'Hosting', 'kind' => 'hosting', 'is_active' => true]);
        $group = ProductGroup::create(['name' => 'Bisnis', 'slug' => 'bisnis', 'product_type_id' => $type->id, 'is_active' => true]);
        $product = Product::create([
            'product_category_id' => $group->id, 'name' => 'Paket Satu', 'slug' => 'paket-satu',
            'domain_option' => 'none', 'price_monthly' => 25000, 'is_active' => true,
        ]);

        return [$group, $product];
    }

    public function test_store_urls_live_under_store_on_the_client_domain(): void
    {
        foreach (['store.hosting' => '/store/hosting', 'store.vps' => '/store/vps', 'store.domain' => '/store/domain',
            'store.transfer' => '/store/transfer-domain', 'store.license' => '/store/lisensi',
            'store.domain-premium' => '/store/domain-premium'] as $name => $path) {
            $this->assertSame($path, parse_url(route($name), PHP_URL_PATH), $name);
            $this->assertSame(config('portal_domains.client'), parse_url(route($name), PHP_URL_HOST), $name);
        }
    }

    public function test_every_store_page_opens_for_a_logged_in_client_inside_the_store_layout(): void
    {
        $this->login();

        foreach (['store.hosting', 'store.vps', 'store.license', 'store.domain', 'store.transfer'] as $name) {
            $this->get(route($name))
                ->assertOk()
                ->assertSee('store-topbar', false)
                ->assertDontSee('id="clientTopbar"', false);
        }
    }

    public function test_domain_premium_follows_the_same_activation_rule_as_the_public_page(): void
    {
        // Halaman ini hanya terbuka bila menunya aktif di admin; Store mengikuti aturan yang sama.
        $this->login();
        $inStore = $this->get(route('store.domain-premium'))->getStatusCode();

        auth('client')->logout();
        $public = $this->get(route('domain-premium.index'))->getStatusCode();

        $this->assertSame($public, $inStore);
    }

    public function test_links_inside_store_pages_stay_inside_the_store(): void
    {
        $this->login();

        $this->get(route('store.hosting'))
            ->assertOk()
            ->assertSee(route('store.domain'), false)
            ->assertDontSee(route('catalog.vps'), false)
            ->assertDontSee(route('domain.search'), false);

        $this->get(route('store.domain'))
            ->assertOk()
            ->assertSee(route('store.transfer'), false)
            ->assertDontSee(route('domains.transfer'), false);
    }

    public function test_category_and_product_pages_work_under_store_with_store_links(): void
    {
        [$group, $product] = $this->product();
        $this->login();
        $this->get(route('store.index')); // permintaan di domain member

        $this->assertSame('/store/hosting/bisnis', parse_url($group->publicUrl(), PHP_URL_PATH));
        $this->assertSame('/store/hosting/bisnis/paket-satu', parse_url($group->productUrl($product), PHP_URL_PATH));

        $this->get(route('store.category', ['hosting', 'bisnis']))
            ->assertOk()
            ->assertSee('Paket Satu')
            ->assertSee(route('store.product', ['hosting', 'bisnis', 'paket-satu']), false);

        $this->get(route('store.product', ['hosting', 'bisnis', 'paket-satu']))
            ->assertOk()
            ->assertSee('Paket Satu')
            // Form pesan tetap mengirim ke keranjang di portal member.
            ->assertSee(route('cart.add-product'), false)
            ->assertDontSee(route('catalog.index'), false);
    }

    public function test_the_header_highlights_the_active_store_section(): void
    {
        $this->login();

        $this->get(route('store.license'))
            ->assertOk()
            ->assertSeeInOrder(['store-topbar__link is-active', 'Lisensi'], false);

        $html = $this->get(route('store.vps'))->getContent();
        $this->assertMatchesRegularExpression('/is-active"\s+aria-current="page"\s*><i class="fa-solid fa-cloud"><\/i> VPS/', $html);
        $this->assertDoesNotMatchRegularExpression('/is-active"[^>]*><i class="fa-solid fa-server"><\/i> Hosting/', $html);
    }

    public function test_guests_are_sent_to_the_matching_public_page(): void
    {
        [$group, $product] = $this->product();

        $this->get(route('store.hosting'))->assertRedirect(route('catalog.index'));
        $this->get(route('store.vps'))->assertRedirect(route('catalog.vps'));
        $this->get(route('store.license'))->assertRedirect(route('license.index'));
        $this->get(route('store.transfer'))->assertRedirect(route('domains.transfer'));
        $this->get(route('store.domain', ['domain' => 'contoh']))->assertRedirect(route('domain.search').'?domain=contoh');
        $this->get(route('store.category', ['hosting', 'bisnis']))->assertRedirect(route('catalog.category', ['hosting', 'bisnis']));
        $this->get(route('store.product', ['hosting', 'bisnis', 'paket-satu']))
            ->assertRedirect(route('catalog.product', ['hosting', 'bisnis', 'paket-satu']));
    }

    public function test_public_pages_are_untouched_for_guests(): void
    {
        $this->get(route('catalog.index'))->assertOk()->assertDontSee('/store/hosting', false);
        $this->assertFalse(StoreUrl::inMemberStore());
        $this->assertSame(route('catalog.vps'), StoreUrl::route('catalog.vps'));
    }

    public function test_store_url_helper_maps_only_for_a_logged_in_client_on_the_member_domain(): void
    {
        $this->login();

        $this->get(route('store.index')); // permintaan di domain member

        $this->assertTrue(StoreUrl::inMemberStore());
        $this->assertSame(route('store.vps'), StoreUrl::route('catalog.vps'));
        $this->assertSame(route('cart.index'), StoreUrl::route('cart.index')); // di luar peta: apa adanya
    }

    public function test_failures_from_the_public_site_still_return_to_a_public_url(): void
    {
        [$group, $product] = $this->product();

        $this->assertSame(
            route('catalog.product', ['hosting', 'bisnis', 'paket-satu']),
            $group->productUrl($product, public: true)
        );
    }

    public function test_every_store_route_has_a_public_counterpart(): void
    {
        foreach (StoreUrl::MAP as $public => $store) {
            $this->assertTrue(\Illuminate\Support\Facades\Route::has($public), $public);
            $this->assertTrue(\Illuminate\Support\Facades\Route::has($store), $store);
            $this->assertSame($public, StoreUrl::publicName($store));
        }
    }
}
