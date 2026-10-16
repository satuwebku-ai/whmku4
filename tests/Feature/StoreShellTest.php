<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Header belanja (judul, pintasan etalase, langkah pemesanan) di portal client. */
class StoreShellTest extends TestCase
{
    public function test_hero_shows_title_subtitle_and_storefront_shortcuts(): void
    {
        $html = view('partials.store-hero', [
            'title' => 'Keranjang Belanja', 'subtitle' => '2 item siap dipesan.', 'step' => 1, 'chips' => true,
        ])->render();

        $this->assertStringContainsString('Keranjang Belanja', $html);
        $this->assertStringContainsString('2 item siap dipesan.', $html);

        foreach (['catalog.index', 'catalog.vps', 'domain.search', 'license.index'] as $route) {
            $this->assertStringContainsString('href="'.route($route).'"', $html, $route);
        }
    }

    public function test_shortcuts_and_steps_are_optional(): void
    {
        $html = view('partials.store-hero', ['title' => 'Konfirmasi Pesanan'])->render();

        $this->assertStringNotContainsString('store-chip', $html);
        $this->assertStringNotContainsString('store-steps', $html);
    }

    public function test_steps_mark_done_active_and_upcoming(): void
    {
        $html = view('partials.store-steps', ['step' => 2])->render();

        $this->assertStringContainsString('<a href="'.route('cart.index').'" class="store-step is-done">', $html);
        $this->assertSame(1, substr_count($html, 'aria-current="step"'));
        $this->assertSame(1, substr_count($html, 'store-step is-active'));
        $this->assertSame(1, substr_count($html, 'store-step is-done'));
        $this->assertStringContainsString('<span class="store-step ">', $html); // langkah 3: belum dicapai
        $this->assertStringContainsString('fa-check', $html);
    }

    public function test_step_three_has_no_link_back_to_the_now_empty_cart(): void
    {
        $html = view('partials.store-steps', ['step' => 3])->render();

        $this->assertStringNotContainsString('href="'.route('cart.index').'"', $html);
        $this->assertSame(2, substr_count($html, 'store-step is-done'));
        $this->assertSame(1, substr_count($html, 'store-step is-active'));
    }

    public function test_checkout_flags_completion_and_every_theme_invoice_view_shows_step_three(): void
    {
        $this->assertStringContainsString("with('checkout_completed', true)",
            file_get_contents(app_path('Http/Controllers/Client/CheckoutController.php')));

        foreach (glob(resource_path('views/themes/client-themes/*/client/invoices/show.blade.php')) as $file) {
            $source = file_get_contents($file);

            $this->assertStringContainsString("session('checkout_completed')", $source, $file);
            $this->assertStringContainsString("'step'     => 3", $source, $file);
        }
    }

    public function test_every_client_theme_checkout_uses_the_shared_header(): void
    {
        foreach (glob(resource_path('views/themes/client-themes/*/client/checkout/index.blade.php')) as $file) {
            $this->assertStringContainsString("@include('partials.store-hero'", file_get_contents($file), $file);
        }
    }
}
