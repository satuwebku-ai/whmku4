<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Header store: mengikuti tema client yang aktif, dan punya varian tamu. */
class StoreTopbarTest extends TestCase
{
    use RefreshDatabase;

    private function render(string $theme = 'default', ?Client $client = null, int $cart = 0): string
    {
        Setting::put('client_template', $theme);

        return view('partials.store-topbar', ['siteName' => 'Satu Cloud', 'client' => $client, 'cartCount' => $cart])->render();
    }

    public function test_each_client_theme_gets_its_own_variant(): void
    {
        foreach (['default', 'modern', 'namahost'] as $theme) {
            $this->assertStringContainsString("store-topbar--{$theme}", $this->render($theme), $theme);
        }
    }

    public function test_every_variant_defines_the_full_set_of_theme_variables(): void
    {
        $html = $this->render();

        foreach (['default', 'modern', 'namahost', 'light'] as $variant) {
            $this->assertMatchesRegularExpression("/\\.store-topbar--{$variant}\\s*\\{[^}]*--st-bg:[^}]*--st-cta-bg:[^}]*--st-height:/s", $html, $variant);
        }
    }

    public function test_the_native_look_of_each_theme_is_reused(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('#1e1b4b', $html);   // gradien topbar tema Default
        $this->assertStringContainsString('250, 249, 246', $html); // krem translusen tema Modern
        $this->assertStringContainsString('#0b2a33', $html);   // navy sidebar tema Namahost
    }

    public function test_guests_see_login_and_register_instead_of_dashboard_and_account(): void
    {
        $html = $this->render('modern', null);

        $this->assertStringContainsString('href="'.route('client.login').'"', $html);
        $this->assertStringContainsString('href="'.route('client.register').'"', $html);
        $this->assertStringContainsString('href="'.route('home').'"', $html, 'merek mengarah ke beranda');
        $this->assertStringNotContainsString('Dashboard', $html);
        $this->assertStringNotContainsString('store-topbar__avatar" ', $html);
    }

    public function test_clients_see_dashboard_and_account_instead_of_login(): void
    {
        $client = Client::factory()->make(['name' => 'Budi Santoso']);

        $html = $this->render('namahost', $client);

        $this->assertStringContainsString('Dashboard', $html);
        $this->assertStringContainsString('alt="Budi Santoso"', $html);
        $this->assertStringNotContainsString(route('client.register'), $html);
    }

    public function test_the_cart_badge_reflects_the_count(): void
    {
        $this->assertMatchesRegularExpression('/id="cartBadge" class="store-topbar__badge "[^>]*>3</', $this->render('default', null, 3));
        $this->assertMatchesRegularExpression('/id="cartBadge" class="store-topbar__badge d-none"[^>]*>0</', $this->render('default', null, 0));
    }

    public function test_all_four_storefront_shortcuts_are_present_for_desktop_and_mobile(): void
    {
        $html = $this->render();

        foreach (['catalog.index', 'catalog.vps', 'domain.search', 'license.index'] as $route) {
            $this->assertSame(2, substr_count($html, 'href="'.route($route).'"'), $route);
        }
    }
}
