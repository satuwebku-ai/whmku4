<?php

namespace Tests\Feature\Domain;

use App\Models\CustomPremiumDomain;
use App\Models\Registrar;
use App\Models\Tld;
use App\Models\TldPremium;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DomainSearchPremiumClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_premium_search_result_uses_its_own_price_and_cart_route(): void
    {
        $this->createTld('.com', 120000);
        CustomPremiumDomain::create([
            'domain_name' => 'brand.com',
            'label' => 'brand',
            'extension' => '.com',
            'characters' => 5,
            'years' => 1,
            'cost_price' => 250000,
            'sell_price' => 375000,
            'is_active' => true,
        ]);

        Http::fake([
            'https://rdap.org/domain/*' => Http::response([], 404),
        ]);

        $this->get(route('domain.search', ['domain' => 'brand']))
            ->assertOk()
            ->assertSee('PREMIUM KHUSUS')
            ->assertSee('Rp 375.000')
            ->assertSee(route('cart.add-custom-premium'), false)
            ->assertSee('name="custom_premium_id"', false)
            ->assertDontSee('name="domain_name" value="brand.com"', false);
    }

    public function test_custom_premium_admin_price_overrides_id_tier_and_regular_prices(): void
    {
        $registrar = Registrar::create([
            'name' => 'DNAMA Custom Override Test',
            'provider' => 'dnama',
            'api_url' => 'https://api.dnama.test',
            'api_username' => 'test-user',
            'api_key' => 'test-key',
            'is_active' => true,
        ]);

        $this->createTld('.id', 235000, $registrar->id);
        TldPremium::create([
            'registrar_id' => $registrar->id,
            'extension' => '.id',
            'is_premium' => true,
            'max_premium_character' => 2,
            'label' => '.id (2 karakter) Premium',
            'is_generic' => false,
            'sell_register_price' => 8500000,
            'is_active' => true,
        ]);

        CustomPremiumDomain::create([
            'domain_name' => 'ab.id',
            'label' => 'ab',
            'extension' => '.id',
            'characters' => 2,
            'years' => 1,
            'cost_price' => 5000000,
            'sell_price' => 9000000,
            'is_active' => true,
        ]);

        Http::fake([
            'https://rdap.org/domain/*' => Http::response([], 404),
        ]);

        $this->get(route('domain.search', ['domain' => 'ab']))
            ->assertOk()
            ->assertSee('PREMIUM KHUSUS')
            ->assertSee('Rp 9.000.000')
            ->assertDontSee('Rp 8.500.000')
            ->assertSee(route('cart.add-custom-premium'), false)
            ->assertDontSee('name="tld_premium_id"', false)
            ->assertDontSee('name="domain_name" value="ab.id"', false);
    }

    public function test_regular_domain_search_result_keeps_regular_price_and_cart_route(): void
    {
        $this->createTld('.com', 120000);

        Http::fake([
            'https://rdap.org/domain/*' => Http::response([], 404),
        ]);

        $this->get(route('domain.search', ['domain' => 'example']))
            ->assertOk()
            ->assertSee('Rp 120.000')
            ->assertSee(route('domain.add-to-cart'), false)
            ->assertDontSee('PREMIUM KHUSUS')
            ->assertDontSee('Tambah Premium');
    }

    public function test_registered_domain_is_not_reclassified_as_premium_or_unknown(): void
    {
        $registrar = Registrar::create([
            'name' => 'DNAMA Taken Domain Test',
            'provider' => 'dnama',
            'api_url' => 'https://api.dnama.test',
            'api_username' => 'test-user',
            'api_key' => 'test-key',
            'is_active' => true,
        ]);

        $this->createTld('.com', 120000, $registrar->id);
        TldPremium::create([
            'registrar_id' => $registrar->id,
            'extension' => '.com',
            'is_premium' => true,
            'label' => '.com Premium',
            'is_generic' => true,
            'is_active' => true,
        ]);
        CustomPremiumDomain::create([
            'domain_name' => 'sandra.com',
            'label' => 'sandra',
            'extension' => '.com',
            'characters' => 6,
            'years' => 1,
            'cost_price' => 5000000,
            'sell_price' => 9000000,
            'is_active' => true,
        ]);

        Cache::forget('rdap:sandra.com');
        Http::fake([
            'https://rdap.org/domain/*' => Http::response(['ldhName' => 'sandra.com'], 200),
        ]);

        $this->get(route('domain.search', [
            'domain' => 'sandra',
            'extensions' => ['.com'],
        ]))
            ->assertOk()
            ->assertSee('sandra.com')
            ->assertSee('Tidak Tersedia')
            ->assertDontSee('PREMIUM KHUSUS')
            ->assertDontSee('PREMIUM PER NAMA')
            ->assertDontSee('CEK PREMIUM ULANG')
            ->assertDontSee('Perlu Dicek Manual')
            ->assertDontSee('Rp 9.000.000')
            ->assertDontSee(route('domain.add-to-cart'), false);
    }

    public function test_generic_premium_from_admin_extension_list_is_not_sent_to_regular_cart(): void
    {
        $registrar = Registrar::create([
            'name' => 'DNAMA Generic Test',
            'provider' => 'dnama',
            'api_url' => 'https://api.dnama.test',
            'api_username' => 'test-user',
            'api_key' => 'test-key',
            'is_active' => true,
        ]);

        $this->createTld('.com', 120000, $registrar->id);
        TldPremium::create([
            'registrar_id' => $registrar->id,
            'extension' => '.com',
            'is_premium' => true,
            'label' => '.com Premium',
            'is_generic' => true,
            'is_active' => true,
        ]);

        Http::fake([
            'https://rdap.org/domain/*' => Http::response([], 404),
            'https://api.dnama.test/domain-availability*' => Http::response([
                'data' => ['available' => true, 'is_premium' => true],
            ]),
        ]);

        $this->get(route('domain.search', ['domain' => 'genericpremium', 'extensions' => ['.com']]))
            ->assertOk()
            ->assertSee('PREMIUM PER NAMA')
            ->assertSee('Harga dan pemesanan premium ditentukan per nama')
            ->assertSee('Pemesanan manual')
            ->assertDontSee(route('domain.add-to-cart'), false);
    }

    public function test_generic_extension_stays_regular_when_registrar_says_not_premium(): void
    {
        $registrar = Registrar::create([
            'name' => 'DNAMA Generic Regular Test',
            'provider' => 'dnama',
            'api_url' => 'https://api.dnama.test',
            'api_username' => 'test-user',
            'api_key' => 'test-key',
            'is_active' => true,
        ]);

        $this->createTld('.com', 120000, $registrar->id);
        TldPremium::create([
            'registrar_id' => $registrar->id,
            'extension' => '.com',
            'is_premium' => true,
            'label' => '.com Premium',
            'is_generic' => true,
            'is_active' => true,
        ]);

        Http::fake([
            'https://rdap.org/domain/*' => Http::response([], 404),
            'https://api.dnama.test/domain-availability*' => Http::response([
                'data' => ['available' => true, 'is_premium' => false],
            ]),
        ]);

        $this->get(route('domain.search', ['domain' => 'regulargeneric', 'extensions' => ['.com']]))
            ->assertOk()
            ->assertSee('Rp 120.000')
            ->assertSee(route('domain.add-to-cart'), false)
            ->assertDontSee('PREMIUM PER NAMA');
    }

    public function test_generic_domain_is_not_added_at_regular_price_when_premium_check_fails(): void
    {
        $registrar = Registrar::create([
            'name' => 'DNAMA Generic Failure Test',
            'provider' => 'dnama',
            'api_url' => 'https://api.dnama.test',
            'api_username' => 'test-user',
            'api_key' => 'test-key',
            'is_active' => true,
        ]);

        $this->createTld('.com', 120000, $registrar->id);
        TldPremium::create([
            'registrar_id' => $registrar->id,
            'extension' => '.com',
            'is_premium' => true,
            'label' => '.com Premium',
            'is_generic' => true,
            'is_active' => true,
        ]);

        Http::fake([
            'https://rdap.org/domain/*' => Http::response([], 404),
            'https://api.dnama.test/domain-availability*' => Http::response([], 500),
        ]);

        $this->get(route('domain.search', ['domain' => 'premiumunknown', 'extensions' => ['.com']]))
            ->assertOk()
            ->assertSee('CEK PREMIUM ULANG')
            ->assertSee('Status premium belum terverifikasi oleh registrar')
            ->assertSee('Perlu Dicek Manual')
            ->assertDontSee(route('domain.add-to-cart'), false);
    }

    public function test_id_family_character_tier_uses_admin_sale_price_even_if_dnama_says_not_premium(): void
    {
        $registrar = Registrar::create([
            'name' => 'DNAMA PANDI Tier Test',
            'provider' => 'dnama',
            'api_url' => 'https://api.dnama.test',
            'api_username' => 'test-user',
            'api_key' => 'test-key',
            'is_active' => true,
        ]);

        $extensions = [
            '.co.id' => 18100000,
            '.my.id' => 15700000,
            '.web.id' => 12000000,
        ];

        foreach ($extensions as $extension => $sellPrice) {
            $this->createTld($extension, 20000, $registrar->id);
            TldPremium::create([
                'registrar_id' => $registrar->id,
                'extension' => $extension,
                'is_premium' => true,
                'max_premium_character' => 2,
                'label' => "{$extension} (2 karakter) Premium",
                'is_generic' => false,
                'cost_register' => 1000000,
                'sell_register_price' => $sellPrice,
                'is_active' => true,
            ]);
        }

        Http::fake([
            'https://rdap.org/domain/*' => Http::response([], 404),
            'https://api.dnama.test/domain-availability*' => Http::response([
                'data' => ['available' => true, 'is_premium' => false],
            ]),
        ]);

        $response = $this->get(route('domain.search', [
            'domain' => 'sa',
            'extensions' => array_keys($extensions),
        ]))->assertOk();

        foreach ($extensions as $extension => $sellPrice) {
            $response
                ->assertSee("sa{$extension}")
                ->assertSee('Rp ' . number_format($sellPrice, 0, ',', '.'));
        }

        $response->assertSee('PREMIUM');

        foreach (array_keys($extensions) as $extension) {
            $response->assertDontSee('name="domain_name" value="sa' . $extension . '"', false);
        }
    }

    public function test_id_domain_longer_than_configured_character_tier_keeps_regular_price(): void
    {
        $registrar = Registrar::create([
            'name' => 'DNAMA PANDI Length Test',
            'provider' => 'dnama',
            'api_url' => 'https://api.dnama.test',
            'api_username' => 'test-user',
            'api_key' => 'test-key',
            'is_active' => true,
        ]);

        $this->createTld('.my.id', 20000, $registrar->id);
        TldPremium::create([
            'registrar_id' => $registrar->id,
            'extension' => '.my.id',
            'is_premium' => true,
            'max_premium_character' => 2,
            'label' => '.my.id (2 karakter) Premium',
            'is_generic' => false,
            'sell_register_price' => 15700000,
            'is_active' => true,
        ]);

        Http::fake([
            'https://rdap.org/domain/*' => Http::response([], 404),
            'https://api.dnama.test/domain-availability*' => Http::response([
                'data' => ['available' => true, 'is_premium' => false],
            ]),
        ]);

        $this->get(route('domain.search', [
            'domain' => 'nama',
            'extensions' => ['.my.id'],
        ]))
            ->assertOk()
            ->assertSee('nama.my.id')
            ->assertSee('Rp 20.000')
            ->assertSee(route('domain.add-to-cart'), false)
            ->assertDontSee('PREMIUM');
    }

    public function test_registry_detected_name_premium_is_caught_outside_fixed_tiers(): void
    {
        $registrar = Registrar::create([
            'name' => 'DNAMA Per-name Premium Test',
            'provider' => 'dnama',
            'api_url' => 'https://api.dnama.test',
            'api_username' => 'test-user',
            'api_key' => 'test-key',
            'is_active' => true,
        ]);

        $this->createTld('.id', 235000, $registrar->id);
        $this->createTld('.sbs', 10800000, $registrar->id);
        TldPremium::create([
            'registrar_id' => $registrar->id,
            'extension' => '.id',
            'is_premium' => true,
            'max_premium_character' => 4,
            'label' => '.id (4 karakter) Premium',
            'is_generic' => false,
            'sell_register_price' => 2000000,
            'is_active' => true,
        ]);

        Http::fake([
            'https://rdap.org/domain/*' => Http::response([], 404),
            'https://api.dnama.test/domain-availability*' => Http::response([
                'data' => ['available' => true, 'is_premium' => true],
            ]),
        ]);

        $response = $this->get(route('domain.search', [
            'domain' => 'sandra',
            'extensions' => ['.id', '.sbs'],
        ]))->assertOk();

        $response
            ->assertSee('sandra.id')
            ->assertSee('sandra.sbs')
            ->assertSee('PREMIUM PER NAMA')
            ->assertSee('Harga dan pemesanan premium ditentukan per nama');

        $response
            ->assertDontSee('name="domain_name" value="sandra.id"', false)
            ->assertDontSee('name="domain_name" value="sandra.sbs"', false);
    }

    public function test_id_family_premium_search_result_uses_the_premium_tier(): void
    {
        $registrar = Registrar::create([
            'name' => 'DNAMA Test',
            'provider' => 'dnama',
            'api_url' => 'https://api.dnama.test',
            'api_username' => 'test-user',
            'api_key' => 'test-key',
            'is_active' => true,
        ]);

        $this->createTld('.id', 250000, $registrar->id);
        TldPremium::create([
            'registrar_id' => $registrar->id,
            'extension' => '.id',
            'is_premium' => true,
            'max_premium_character' => 2,
            'label' => '* .id (2 karakter) Premium',
            'is_generic' => false,
            'cost_register' => 3000000,
            'sell_register_price' => 8500000,
            'is_active' => true,
        ]);

        Http::fake([
            'https://rdap.org/domain/*' => Http::response([], 404),
            'https://api.dnama.test/domain-availability*' => Http::response([
                'data' => ['available' => true, 'is_premium' => true],
            ]),
        ]);

        $this->get(route('domain.search', ['domain' => 'ab.id']))
            ->assertOk()
            ->assertSee('PREMIUM')
            ->assertSee('Rp 8.500.000')
            ->assertDontSee('Rp 3.000.000')
            ->assertSee(route('cart.add-premium-domain'), false)
            ->assertSee('name="tld_premium_id"', false)
            ->assertDontSee('name="domain_name" value="ab.id"', false);
    }

    private function createTld(string $extension, float $price, ?int $registrarId = null): Tld
    {
        return Tld::create([
            'extension' => $extension,
            'registrar_id' => $registrarId,
            'register_price' => $price,
            'renew_price' => $price,
            'transfer_price' => $price,
            'is_active' => true,
            'show_in_search' => true,
            'search_group' => 'Populer',
        ]);
    }
}
