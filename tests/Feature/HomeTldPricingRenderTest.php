<?php

namespace Tests\Feature;

use App\Models\Tld;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Render nyata beranda tema NamaHost: chip ekstensi, pilihan durasi, dan
 * "Hemat X%" harus konsisten dengan Tld::priceForYears() (angka yang sama
 * dipakai keranjang/checkout).
 */
class HomeTldPricingRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Tema dipilih saat boot dari tabel settings; di test kita arahkan manual.
        View::getFinder()->prependLocation(resource_path('views/themes/public-themes/namahost'));
    }

    private function tld(string $ext, float $price, array $extra = []): Tld
    {
        return Tld::create(array_merge([
            'extension'      => $ext,
            'register_price' => $price,
            'renew_price'    => $price,
            'transfer_price' => $price,
            'is_active'      => true,
            'show_in_search' => true,
            'min_years'      => 1,
            'max_years'      => 10,
        ], $extra));
    }

    public function test_home_renders_chips_prices_and_saving_badge(): void
    {
        $this->tld('.my.id', 20000, ['year_prices' => ['2' => 36000, '3' => 51000]]);
        $this->tld('.sch.id', 54000);                                  // linier, tanpa hemat
        $this->tld('.web.id', 20000, ['show_in_search' => false]);     // tampil, tak bisa dicentang
        $this->tld('.mati.id', 10000, ['is_active' => false]);         // nonaktif: tidak tampil

        $html = $this->get('/')->assertOk()->getContent();

        // Chip + harga
        $this->assertStringContainsString('.my.id', $html);
        $this->assertStringContainsString('Rp20.000', $html);
        $this->assertStringContainsString('Rp54.000', $html);
        $this->assertStringNotContainsString('.mati.id', $html);

        // Checkbox hanya untuk TLD yang show_in_search
        $this->assertStringContainsString('name="extensions[]" value=".my.id"', $html);
        $this->assertStringContainsString('name="extensions[]" value=".sch.id"', $html);
        $this->assertStringNotContainsString('value=".web.id"', $html);

        // Pilihan durasi muncul karena ada TLD dengan >1 opsi
        $this->assertStringContainsString('data-duration-group', $html);
        $this->assertStringContainsString('tldDur2', $html);

        // Data opsi per baris: .my.id 2 tahun = 36.000 (hemat 10%), 3 tahun = 51.000 (hemat 15%)
        $this->assertMatchesRegularExpression('/data-options="[^"]*&quot;percent&quot;:10/', $html);
    }

    public function test_no_saving_badge_when_multi_year_is_not_cheaper(): void
    {
        $this->tld('.sch.id', 54000);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('Hemat 0%', $html);
        $this->assertDoesNotMatchRegularExpression('/&quot;percent&quot;:[1-9]/', $html);
    }
}
