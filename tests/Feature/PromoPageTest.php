<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Client;
use App\Models\Coupon;
use App\Models\PromoBanner;
use App\Models\Tld;
use App\Services\Billing\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Promo per ekstensi domain: kupon (tabel yang sudah ada) bisa diarahkan ke satu
 * atau beberapa TLD, tetap lewat kode, dan tampil di halaman /promo.
 */
class PromoPageTest extends TestCase
{
    use RefreshDatabase;

    private function tld(string $ext, float $price, array $extra = []): Tld
    {
        return Tld::create(array_merge([
            'extension' => $ext, 'register_price' => $price, 'renew_price' => $price,
            'transfer_price' => $price, 'is_active' => true, 'show_in_search' => true,
            'min_years' => 1, 'max_years' => 10,
        ], $extra));
    }

    private function coupon(array $attrs = []): Coupon
    {
        return Coupon::create(array_merge([
            'code' => 'MYID50', 'type' => 'percent', 'value' => 50, 'applies_to' => 'specific',
            'usage_limit_per_client' => 1, 'is_active' => true,
        ], $attrs));
    }

    private function domainItem(Tld $tld, float $base, float $privacy = 0, string $mode = 'register'): array
    {
        return [
            'key' => uniqid(), 'type' => 'domain', 'tld_id' => $tld->id, 'domain_mode' => $mode,
            'years' => 1, 'base_price' => $base, 'price' => $base + $privacy, 'whois_privacy' => $privacy > 0,
        ];
    }

    // ── Aturan kupon ─────────────────────────────────────────────────

    public function test_tld_coupon_discounts_only_registration_price_of_chosen_tlds(): void
    {
        $myid = $this->tld('.my.id', 20000);
        $com  = $this->tld('.com', 150000);
        $c = $this->coupon(['tld_ids' => [$myid->id]]);

        $items = [
            $this->domainItem($myid, 20000, 5000),                 // ID Protection tidak ikut
            $this->domainItem($com, 150000),                        // TLD lain tidak ikut
            $this->domainItem($myid, 20000, 0, 'transfer'),         // transfer tidak ikut
        ];

        $this->assertSame(20000.0, $c->eligibleSubtotal($items));
        $this->assertSame(10000.0, (new CouponService())->discountFor($c, $items));
    }

    public function test_product_only_coupon_and_all_coupon_keep_their_old_behaviour(): void
    {
        $myid = $this->tld('.my.id', 20000);
        $items = [$this->domainItem($myid, 20000)];

        $specificNoTld = $this->coupon(['code' => 'PROD', 'tld_ids' => null]);
        $this->assertSame(0.0, $specificNoTld->eligibleSubtotal($items));   // domain tetap tidak didiskon

        $all = $this->coupon(['code' => 'SEMUA', 'applies_to' => 'all']);
        $this->assertSame(20000.0, $all->eligibleSubtotal($items));         // perilaku lama tidak berubah
    }

    public function test_tld_coupon_is_rejected_when_cart_has_no_matching_domain(): void
    {
        $myid = $this->tld('.my.id', 20000);
        $com  = $this->tld('.com', 150000);
        $c = $this->coupon(['tld_ids' => [$myid->id]]);
        $client = Client::factory()->create();

        $error = (new CouponService())->validationError($c, $client, [$this->domainItem($com, 150000)]);
        $this->assertStringContainsString('tidak berlaku', (string) $error);

        $this->assertNull((new CouponService())->validationError($c, $client, [$this->domainItem($myid, 20000)]));
    }

    public function test_code_is_entered_manually_at_checkout_and_never_applied_automatically(): void
    {
        $myid = $this->tld('.my.id', 20000);
        $c = $this->coupon(['tld_ids' => [$myid->id], 'is_public' => true]);
        $client = Client::factory()->create();
        $cart = ['cart' => [$this->domainItem($myid, 20000)]];

        // Membuka checkout tanpa memasukkan kode: tidak ada diskon.
        $page = $this->actingAs($client, 'client')->withSession($cart)->get(route('client.checkout'));
        $page->assertOk();
        $this->assertNull(session('checkout.coupon_id'));

        // Memasukkan kode: baru diterapkan.
        $this->actingAs($client, 'client')->withSession($cart)
            ->post(route('client.checkout.coupon'), ['code' => 'myid50'])
            ->assertSessionHas('success');
        $this->assertSame($c->id, session('checkout.coupon_id'));
    }

    // ── Halaman publik ───────────────────────────────────────────────

    public function test_promo_page_lists_only_live_public_coupons_with_prices_and_banner(): void
    {
        $myid = $this->tld('.my.id', 20000);
        $this->tld('.web.id', 20000, ['show_in_search' => false]);

        $this->coupon([
            'tld_ids' => [$myid->id], 'is_public' => true, 'title' => 'Diskon .my.id',
            'description' => "Registrasi baru.\n<script>alert(1)</script>",
            'expires_at' => now()->addDays(5), 'usage_limit' => 10, 'usage_count' => 4,
        ]);
        $this->coupon(['code' => 'RAHASIA', 'tld_ids' => [$myid->id], 'is_public' => false]);
        $this->coupon(['code' => 'KEDALUWARSA', 'tld_ids' => [$myid->id], 'is_public' => true, 'expires_at' => now()->subDay()]);
        $this->coupon(['code' => 'HABIS', 'tld_ids' => [$myid->id], 'is_public' => true, 'usage_limit' => 3, 'usage_count' => 3]);
        $this->coupon(['code' => 'MATI', 'tld_ids' => [$myid->id], 'is_public' => true, 'is_active' => false]);

        PromoBanner::create(['title' => 'Banner Promo Oktober', 'image' => 'x.jpg', 'display_page' => 'promo', 'is_active' => true]);
        PromoBanner::create(['title' => 'Banner Beranda', 'image' => 'y.jpg', 'display_page' => 'home', 'is_active' => true]);

        $html = $this->get(route('promo.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Diskon .my.id', $html);
        $this->assertStringContainsString('MYID50', $html);
        $this->assertStringContainsString('Diskon 50%', $html);
        $this->assertStringContainsString('Rp20.000', $html);     // harga normal
        $this->assertStringContainsString('Rp10.000', $html);     // harga setelah kode
        $this->assertStringContainsString('Sisa kuota: 6', $html);
        // Harga normal dicoret, harga setelah kode ditampilkan, dan .web.id (bukan sasaran) tidak muncul.
        $this->assertStringContainsString('text-decoration-line-through text-muted ms-1">Rp20.000', $html);
        $this->assertStringNotContainsString('.web.id', $html);
        // Tombol Cek domain mencentang ekstensi promo.
        $this->assertStringContainsString('cek-domain?extensions%5B0%5D=.my.id', $html);
        // Kode dapat disalin lewat aksi CSP bawaan (tanpa handler inline).
        $this->assertStringContainsString('data-action="copy" data-target="promoCode', $html);
        $this->assertDoesNotMatchRegularExpression('/\son(click|change|submit)=/i', $html);

        foreach (['RAHASIA', 'KEDALUWARSA', 'HABIS', 'MATI'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $html);
        }

        // Keterangan admin di-escape (tidak bisa menyisipkan HTML/skrip).
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);

        // Hanya banner untuk halaman promo.
        $this->assertStringContainsString('Banner Promo Oktober', $html);
        $this->assertStringNotContainsString('Banner Beranda', $html);
    }

    public function test_promo_page_shows_empty_state(): void
    {
        $this->get(route('promo.index'))->assertOk()->assertSee('Belum ada promo aktif');
    }

    public function test_promo_page_is_themed_by_every_public_theme(): void
    {
        \Illuminate\Support\Facades\View::getFinder()->prependLocation(resource_path('views/themes/public-themes/namahost'));
        $this->get(route('promo.index'))->assertOk()->assertSee('Promo &amp; Diskon', false);
    }

    // ── Admin ────────────────────────────────────────────────────────

    private function admin(): Admin
    {
        return Admin::create([
            'username' => 'promo', 'name' => 'Promo', 'email' => 'promo@contoh.test',
            'password' => bcrypt('Rahasia-123'), 'role' => 'superadmin', 'is_active' => true,
        ]);
    }

    public function test_admin_can_create_tld_promo_coupon_and_form_lists_tlds(): void
    {
        $a = $this->tld('.my.id', 20000);
        $b = $this->tld('.biz.id', 20000);
        $this->actingAs($this->admin(), 'admin');

        $this->get(route('admin.coupon.add.page'))->assertOk()->assertSee('.my.id')->assertSee('Tampilkan di halaman Promo publik');

        $this->post(route('admin.coupon.add'), [
            'code' => 'idpromo', 'type' => 'percent', 'value' => 30, 'applies_to' => 'specific',
            'tld_ids' => [$a->id, $b->id], 'usage_limit_per_client' => 1,
            'title' => 'Promo .id', 'description' => 'Untuk registrasi baru.', 'is_public' => 1, 'is_active' => 1,
        ])->assertRedirect(route('admin.coupons'));

        $c = Coupon::where('code', 'IDPROMO')->firstOrFail();
        $this->assertSame([$a->id, $b->id], $c->tld_ids);
        $this->assertTrue($c->is_public);
        $this->assertSame('Promo .id', $c->title);

        $this->get(route('admin.coupon.edit.page', $c))->assertOk()->assertSee('Promo .id');
    }

    public function test_specific_coupon_needs_some_target_and_all_coupon_clears_tlds(): void
    {
        $a = $this->tld('.my.id', 20000);
        $this->actingAs($this->admin(), 'admin');

        $this->post(route('admin.coupon.add'), [
            'code' => 'KOSONG', 'type' => 'percent', 'value' => 10, 'applies_to' => 'specific', 'usage_limit_per_client' => 1,
        ])->assertSessionHasErrors('scope');

        $c = $this->coupon(['code' => 'GANTI', 'tld_ids' => [$a->id]]);
        $this->post(route('admin.coupon.update', $c), [
            'code' => 'GANTI', 'type' => 'percent', 'value' => 10, 'applies_to' => 'all',
            'tld_ids' => [$a->id], 'usage_limit_per_client' => 1, 'is_active' => 1,
        ])->assertRedirect();

        $this->assertNull($c->fresh()->tld_ids);
    }

    public function test_promo_page_option_exists_for_banners(): void
    {
        $this->assertArrayHasKey('promo', PromoBanner::PAGES);
    }
}
