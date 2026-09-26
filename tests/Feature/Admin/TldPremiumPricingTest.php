<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Registrar;
use App\Models\TldPremium;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Mengunci perilaku sinkronisasi & tampilan halaman admin Domain Premium
 * (admin/tld/premium-pricing) memakai payload sungguhan dari
 * GET /customer-tld-pricings DNAMA -- sebelumnya fitur ini SAMA SEKALI
 * tidak punya test, jadi regresi (mis. urutan tampil salah) bisa lolos
 * tanpa ketahuan.
 */
class TldPremiumPricingTest extends TestCase
{
    use RefreshDatabase;

    private function sampleDnamaPayload(): array
    {
        // Payload persis seperti yang dikembalikan DNAMA untuk keluarga
        // ".id": satu baris reguler + beberapa baris premium (2 & 3
        // karakter) yang berbagi nama ekstensi sama persis.
        return [
            [
                'tld' => '.id',
                'currency' => 'IDR',
                'is_premium' => false,
                'max_premium_character' => null,
                'pricings' => [
                    ['duration' => 1, 'register_price' => 215000, 'transfer_price' => 215000, 'renewal_price' => 215000, 'restore_price' => 860000],
                    ['duration' => 2, 'register_price' => 430000, 'transfer_price' => 430000, 'renewal_price' => 430000, 'restore_price' => 860000],
                ],
            ],
            [
                'tld' => '.id',
                'currency' => 'IDR',
                'is_premium' => true,
                'max_premium_character' => 2,
                'pricings' => [
                    ['duration' => 1, 'register_price' => 585600000, 'transfer_price' => 215000, 'renewal_price' => 215000, 'restore_price' => 840000],
                ],
            ],
            [
                'tld' => '.id',
                'currency' => 'IDR',
                'is_premium' => true,
                'max_premium_character' => 3,
                'pricings' => [
                    ['duration' => 1, 'register_price' => 18450000, 'transfer_price' => 215000, 'renewal_price' => 215000, 'restore_price' => 860000],
                ],
            ],
        ];
    }

    private function makeDnamaRegistrar(): Registrar
    {
        return Registrar::create([
            'name' => 'DNAMA - Utama',
            'provider' => 'dnama',
            'api_url' => 'https://api.dnama.test',
            'api_username' => 'user',
            'api_key' => 'secret',
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    private function actingAsAdmin(): self
    {
        $admin = Admin::factory()->superadmin()->create();
        $this->actingAs($admin, 'admin');

        return $this;
    }

    public function test_sync_creates_separate_rows_per_premium_character_tier(): void
    {
        Http::fake([
            'api.dnama.test/customer-tld-pricings*' => Http::response($this->sampleDnamaPayload(), 200),
        ]);

        $registrar = $this->makeDnamaRegistrar();

        $response = $this->actingAsAdmin()
            ->post(route('admin.tld.premium-pricing.sync'), ['registrar_id' => $registrar->id]);

        $response->assertRedirect();

        // 3 baris keluarga .id (reguler + 2 karakter + 3 karakter)
        // HARUS jadi 3 baris terpisah, bukan saling menimpa -- ini yang
        // paling sering rusak kalau matching cuma berdasarkan ekstensi.
        $this->assertSame(3, TldPremium::where('registrar_id', $registrar->id)->where('is_generic', false)->count());

        $regular = TldPremium::where('registrar_id', $registrar->id)
            ->where('extension', '.id')->where('is_premium', false)->first();
        $this->assertNotNull($regular);
        $this->assertSame('.id', $regular->label);
        $this->assertSame(215000.0, (float) $regular->cost_register);
        $this->assertNull($regular->max_premium_character);

        $twoChar = TldPremium::where('registrar_id', $registrar->id)
            ->where('extension', '.id')->where('max_premium_character', 2)->first();
        $this->assertNotNull($twoChar);
        $this->assertSame('.id (2 karakter) Premium', $twoChar->label);
        $this->assertSame(585600000.0, (float) $twoChar->cost_register);

        $threeChar = TldPremium::where('registrar_id', $registrar->id)
            ->where('extension', '.id')->where('max_premium_character', 3)->first();
        $this->assertNotNull($threeChar);
        $this->assertSame('.id (3 karakter) Premium', $threeChar->label);
        $this->assertSame(18450000.0, (float) $threeChar->cost_register);

        // Ekstensi generik ikut "diseed" jadi baris referensi.
        $this->assertSame(
            count(TldPremium::GENERIC_EXTENSIONS),
            TldPremium::where('registrar_id', $registrar->id)->where('is_generic', true)->count()
        );
    }

    public function test_resync_never_overwrites_manually_entered_sell_price(): void
    {
        Http::fake([
            'api.dnama.test/customer-tld-pricings*' => Http::response($this->sampleDnamaPayload(), 200),
        ]);

        $registrar = $this->makeDnamaRegistrar();
        $this->actingAsAdmin();

        $this->post(route('admin.tld.premium-pricing.sync'), ['registrar_id' => $registrar->id]);

        $twoChar = TldPremium::where('registrar_id', $registrar->id)
            ->where('extension', '.id')->where('max_premium_character', 2)->firstOrFail();

        $this->post(route('admin.tld.premium-pricing.update'), [
            'registrar_id' => $registrar->id,
            'rows' => [
                $twoChar->id => ['sell_register_price' => 650000000],
            ],
        ]);

        // Sinkron ulang dengan harga MODAL yang berubah dari DNAMA --
        // harga JUAL yang sudah diisi admin harus tetap utuh.
        $updatedPayload = $this->sampleDnamaPayload();
        $updatedPayload[1]['pricings'][0]['register_price'] = 700000000;

        Http::fake([
            'api.dnama.test/customer-tld-pricings*' => Http::response($updatedPayload, 200),
        ]);

        $this->post(route('admin.tld.premium-pricing.sync'), ['registrar_id' => $registrar->id]);

        $twoChar->refresh();
        $this->assertSame(700000000.0, (float) $twoChar->cost_register);
        $this->assertSame(650000000.0, (float) $twoChar->sell_register_price);
    }

    public function test_admin_page_orders_family_and_generic_rows_correctly(): void
    {
        Http::fake([
            'api.dnama.test/customer-tld-pricings*' => Http::response($this->sampleDnamaPayload(), 200),
        ]);

        $registrar = $this->makeDnamaRegistrar();
        $this->actingAsAdmin();

        $this->post(route('admin.tld.premium-pricing.sync'), ['registrar_id' => $registrar->id]);

        $response = $this->get(route('admin.tlds.premium-pricing', ['registrar' => $registrar->id]));
        $response->assertOk();

        $familyLabels = $response->viewData('familyRows')->pluck('label')->all();
        $this->assertSame(['.id', '.id (2 karakter) Premium', '.id (3 karakter) Premium'], $familyLabels);

        // Ekstensi generik HARUS mengikuti urutan TldPremium::GENERIC_EXTENSIONS
        // (.com, .org, .net, ...) -- BUKAN alfabetis (.asia, .biz, .cc, ...) --
        // supaya sama dengan urutan yang dilihat pengunjung di halaman publik.
        $genericExtensions = $response->viewData('genericRows')->pluck('extension')->all();
        $this->assertSame(TldPremium::GENERIC_EXTENSIONS, $genericExtensions);
    }
}