<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\ServerGroup;
use App\Services\Hosting\ServerSelector;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Database\Seeders\ServerProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED = [
        'Basic Shared Hosting'   => 'hosting',
        'Premium Shared Hosting' => 'hosting',
        'Budget VPS'             => 'vps',
        'KVM NVMe VPS'           => 'vps',
        'Dedicated Server'       => 'hosting',
        'Remote Desktop'         => 'vps',
        'Radio Shoutcast'        => 'hosting',
    ];

    public function test_it_creates_the_seven_categories_in_order_under_the_right_product_type(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        $groups = ProductGroup::with('productType')->orderBy('sort_order')->get();

        $this->assertSame(array_keys(self::EXPECTED), $groups->pluck('name')->all());
        foreach ($groups as $group) {
            $this->assertSame(self::EXPECTED[$group->name], $group->productType->kind, $group->name);
        }
        $this->assertSame(7, Product::count());
        $this->assertSame(1, Product::where('product_category_id', $groups->first()->id)->count());
    }

    public function test_nothing_is_sellable_and_no_server_is_invented(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        // Harga/spek hanyalah contoh: tidak boleh bisa dipesan sebelum admin mengaktifkannya.
        $this->assertSame(0, ProductGroup::where('is_active', true)->count());
        $this->assertSame(0, Product::where('is_active', true)->count());
        Product::with('category')->get()->each(fn (Product $p) => $this->assertFalse($p->isPurchasable(), $p->name));

        // Tanpa server/grup server palsu (hostname contoh bisa membocorkan kredensial pelanggan).
        $this->assertSame(0, Server::count());
        $this->assertSame(0, ServerGroup::count());
        $this->assertSame(0, Product::whereNotNull('server_id')->orWhereNotNull('server_group_id')->count());
    }

    public function test_it_is_idempotent_and_never_overwrites_admin_edits(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        $product = Product::where('name', 'Basic 1')->firstOrFail();
        $product->update(['price_monthly' => 31000, 'is_active' => true]);
        ProductGroup::where('slug', 'basic-shared-hosting')->update(['name' => 'Hosting Murah', 'is_active' => true]);

        $this->seed(ProductCatalogSeeder::class);

        $this->assertSame(7, Product::count());
        $this->assertSame(7, ProductGroup::count());
        $this->assertSame('31000.00', Product::where('name', 'Basic 1')->value('price_monthly'));
        $this->assertTrue((bool) Product::where('name', 'Basic 1')->value('is_active'));
        $this->assertSame('Hosting Murah', ProductGroup::where('slug', 'basic-shared-hosting')->value('name'));
    }

    public function test_vps_plans_carry_a_valid_spec_and_remote_desktop_is_windows(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        foreach (['Budget VPS 1', 'KVM NVMe 1', 'RDP Basic'] as $name) {
            $spec = json_decode((string) Product::where('name', $name)->value('panel_package'), true);

            $this->assertIsArray($spec, $name);
            foreach (['vcpu', 'ram', 'disk', 'os_name', 'os_version'] as $key) {
                $this->assertArrayHasKey($key, $spec, "$name.$key");
            }
        }

        $rdp = json_decode((string) Product::where('name', 'RDP Basic')->value('panel_package'), true);
        $this->assertSame('windows', $rdp['os_name']);
    }

    public function test_products_without_a_server_fall_back_to_manual_activation(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        $resolved = app(ServerSelector::class)->resolve(Product::where('name', 'Dedicated Entry')->firstOrFail());

        // Bukan kegagalan diam-diam: jalur "aktivasi manual" memang disediakan sistem.
        $this->assertNull($resolved['server']);
        $this->assertNull($resolved['reason']);
    }

    public function test_it_coexists_with_the_server_product_seeder_in_any_order(): void
    {
        $this->seed(ServerProductSeeder::class);
        $this->seed(ProductCatalogSeeder::class);
        $this->seed(ProductCatalogSeeder::class);

        $this->assertSame(2, \App\Models\ProductType::count(), 'jenis produk dipakai bersama, tidak digandakan');
        $this->assertSame(7, ProductGroup::whereIn('name', array_keys(self::EXPECTED))->count());
    }

    public function test_it_stays_out_of_the_default_seeder_so_production_installs_stay_clean(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, ProductGroup::count());
        $this->assertSame(0, Product::count());
    }
}
