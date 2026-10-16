<?php

namespace Tests\Feature;

use App\Http\Middleware\RequirePortalOrigin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Serah-terima publik -> client: POST dari domain publik ke keranjang di domain
 * client. Dikecualikan dari CSRF, jadi asal permintaan harus dijaga.
 *
 * Catatan: Laravel mematikan verifikasi CSRF saat tes berjalan, jadi
 * pengecualiannya sendiri tidak bisa diuji di sini; yang dijaga adalah
 * kesesuaian daftar pengecualian dengan route (test pertama).
 */
class RequirePortalOriginTest extends TestCase
{
    use RefreshDatabase;

    private const REJECTED = 'Permintaan tidak dikenali. Silakan pilih produk langsung dari situs kami.';

    private function publicOrigin(): string
    {
        return 'https://'.config('portal_domains.public');
    }

    public function test_csrf_exemptions_match_exactly_the_routes_that_check_the_origin(): void
    {
        $guarded = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => in_array(RequirePortalOrigin::class, $r->gatherMiddleware(), true))
            ->map(fn ($r) => $r->uri())
            ->sort()->values()->all();

        $exempt = RequirePortalOrigin::HANDOFF_PATHS;
        sort($exempt);

        $this->assertSame($exempt, $guarded, 'Daftar HANDOFF_PATHS harus sama dengan route yang memakai RequirePortalOrigin.');
    }

    public function test_a_post_from_the_public_site_passes_the_csrf_and_origin_checks(): void
    {
        // Input sengaja tidak valid: yang diuji adalah pintu masuknya (bukan 419,
        // bukan ditolak asal-usulnya); kegagalan validasi tampil di keranjang.
        $this->post(route('cart.add-premium-domain'), [], ['Origin' => $this->publicOrigin()])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error')
            ->assertSessionMissing('error', self::REJECTED);
    }

    public function test_a_post_from_another_site_is_rejected(): void
    {
        $this->post(route('cart.add-product'), ['product_id' => 1, 'billing_cycle' => 'monthly'], ['Origin' => 'https://situs-lain.example'])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error', self::REJECTED);
    }

    public function test_a_post_without_origin_or_referer_is_rejected(): void
    {
        $this->post(route('domain.add-to-cart'), ['domain_name' => 'contoh', 'tld_id' => 1])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error', self::REJECTED);
    }

    public function test_referer_is_used_when_origin_is_missing(): void
    {
        $this->post(route('cart.add-premium-domain'), [], ['Referer' => $this->publicOrigin().'/domain-premium'])
            ->assertSessionMissing('error', self::REJECTED);
    }

    public function test_a_spoofed_origin_that_only_contains_the_portal_host_is_rejected(): void
    {
        $this->post(route('cart.add-premium-domain'), [], ['Origin' => $this->publicOrigin().'.situs-jahat.example'])
            ->assertSessionHas('error', self::REJECTED);
    }
}
