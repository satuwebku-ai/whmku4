<?php

namespace Tests\Feature;

use App\Support\CartBadge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Lencana keranjang di header halaman publik, lewat cookie jumlah dari domain client. */
class CartBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_show_the_count_shared_by_the_client_domain(): void
    {
        $this->withUnencryptedCookie(CartBadge::COOKIE, '3')
            ->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('id="cartBadge" class="">3</span>', false);
    }

    public function test_public_pages_hide_the_badge_without_the_cookie(): void
    {
        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('id="cartBadge" class="d-none">0</span>', false);
    }

    public function test_the_client_domain_publishes_the_count_when_it_changes(): void
    {
        $this->get(route('cart.index'))->assertOk()->assertCookie(CartBadge::COOKIE, '0');
    }

    public function test_the_cookie_is_not_rewritten_when_the_count_is_unchanged(): void
    {
        $this->withUnencryptedCookie(CartBadge::COOKIE, '0')
            ->get(route('cart.index'))
            ->assertOk()
            ->assertCookieMissing(CartBadge::COOKIE);
    }

    public function test_cookie_domain_is_only_set_when_both_domains_share_a_parent(): void
    {
        $this->assertSame(config('portal_domains.public'), CartBadge::cookieDomain());

        config(['portal_domains.client' => 'client.domain-lain.example']);
        $this->assertNull(CartBadge::cookieDomain());
    }
}
