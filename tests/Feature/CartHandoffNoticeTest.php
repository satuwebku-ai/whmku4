<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductType;
use App\Support\CartNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Gagal menambah ke keranjang dari halaman PUBLIK: pengunjung dikembalikan ke
 * halaman publik asal dengan pesan galat dan isian form -- bukan dibuang ke keranjang.
 */
class CartHandoffNoticeTest extends TestCase
{
    use RefreshDatabase;

    private function origin(): array
    {
        return ['Origin' => 'https://'.config('portal_domains.public')];
    }

    private function product(): Product
    {
        $group = ProductGroup::create([
            'name' => 'Shared', 'is_active' => true,
            'product_type_id' => ProductType::where('kind', 'hosting')->value('id'),
        ]);

        return Product::create([
            'product_category_id' => $group->id, 'name' => 'Starter', 'domain_option' => 'optional',
            'price_monthly' => 25000, 'is_active' => true,
        ]);
    }

    /** Permintaan seolah datang dari $ip (klien uji Laravel memakai 127.0.0.1). */
    private function requestFrom(string $ip, array $input = []): Request
    {
        return Request::create('/', 'POST', $input, [], [], ['REMOTE_ADDR' => $ip]);
    }

    private function token(string $url): string
    {
        return urldecode(explode(CartNotice::PARAM.'=', $url)[1]);
    }

    public function test_a_failed_add_returns_to_the_public_product_page_with_a_notice(): void
    {
        $product = $this->product();
        $page = $product->category->productUrl($product);

        $response = $this->post(route('cart.add-product'), [
            'product_id' => $product->id, 'billing_cycle' => 'bogus',
            'domain_name' => 'contoh.com', 'transfer_auth_code' => 'RAHASIA-EPP',
        ], $this->origin());

        $location = (string) $response->headers->get('Location');

        $this->assertStringStartsWith($page.'?'.CartNotice::PARAM.'=', $location);
        $this->assertStringNotContainsString('RAHASIA-EPP', urldecode($location));
        $this->assertStringNotContainsString('contoh.com', urldecode($location), 'payload harus terenkripsi');
    }

    public function test_the_notice_is_restored_on_the_public_host_as_flash_and_old_input(): void
    {
        $product = $this->product();
        $page = $product->category->productUrl($product);

        $url = CartNotice::url($page, 'Siklus tagihan tidak valid.', $this->requestFrom('127.0.0.1', [
            'domain_name' => 'contoh.com', 'billing_cycle' => 'bogus', 'transfer_auth_code' => 'RAHASIA-EPP',
        ]));

        $this->get($url)
            ->assertRedirect($page)
            ->assertSessionHas('error', 'Siklus tagihan tidak valid.')
            ->assertSessionHasInput('domain_name', 'contoh.com')
            ->assertSessionMissing('_old_input.transfer_auth_code');
    }

    public function test_a_notice_made_for_another_visitor_is_ignored(): void
    {
        // Penyerang memalsukan Origin lewat curl, memperoleh token sah berisi teks
        // pilihannya, lalu mengirim tautannya ke korban (IP berbeda).
        $page = $this->product()->category->productUrl(Product::first());
        $forged = CartNotice::url($page, 'Akun Anda diretas! Hubungi +62-800-0000.', $this->requestFrom('198.51.100.5', ['domain_name' => 'x']));

        $this->get($forged)
            ->assertRedirect($page)
            ->assertSessionMissing('error')
            ->assertSessionMissing('_old_input');
    }

    public function test_only_known_form_fields_are_carried_back(): void
    {
        $page = $this->product()->category->productUrl(Product::first());
        $url = CartNotice::url($page, 'Gagal.', $this->requestFrom('127.0.0.1', [
            'domain_name' => 'contoh.com', 'admin' => '1', 'is_staff' => 'true',
        ]));

        $this->get($url)
            ->assertSessionHasInput('domain_name', 'contoh.com')
            ->assertSessionMissing('_old_input.admin')
            ->assertSessionMissing('_old_input.is_staff');
    }

    public function test_a_tampered_or_expired_notice_is_ignored_but_the_url_is_cleaned(): void
    {
        $product = $this->product();
        $page = $product->category->productUrl($product);

        $this->get($page.'?'.CartNotice::PARAM.'=bukan-token')
            ->assertRedirect($page)
            ->assertSessionMissing('error');

        $stale = \Illuminate\Support\Facades\Crypt::encryptString(json_encode(['m' => 'Basi', 'i' => [], 't' => time() - 1000, 'b' => hash('sha256', '127.0.0.1')]));

        $this->get($page.'?'.CartNotice::PARAM.'='.rawurlencode($stale))
            ->assertRedirect($page)
            ->assertSessionMissing('error');
    }

    public function test_an_unknown_product_falls_back_to_the_cart_with_the_error(): void
    {
        $this->post(route('cart.add-product'), ['product_id' => 999999, 'billing_cycle' => 'monthly'], $this->origin())
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error');
    }

    public function test_the_notice_never_points_to_another_domain(): void
    {
        // Alamat tujuan dibuat server dari route publik; di sini memastikan host-nya publik.
        $product = $this->product();

        $response = $this->post(route('cart.add-product'), ['product_id' => $product->id, 'billing_cycle' => 'bogus'], $this->origin());

        $this->assertSame(config('portal_domains.public'), parse_url((string) $response->headers->get('Location'), PHP_URL_HOST));
    }
}
