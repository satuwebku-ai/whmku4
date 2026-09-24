<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Produk dikelompokkan VPS vs Hosting lewat SATU sumber (kategori type='vps',
 * dengan server cloud sebagai jaring pengaman) -- Product::scopeVpsType() /
 * scopeHostingType(). Ini mencegah bug produk VPS nyasar tampil di section
 * "Paket Hosting Pilihan" pada halaman depan.
 */
class ProductVpsClassificationTest extends TestCase
{
    use RefreshDatabase;

    private function cloudServer(): Server
    {
        return Server::create([
            'name' => 'Cloud 1', 'panel' => 'vps', 'vps_provider' => 'idcloudhost',
            'hostname' => '', 'api_username' => '', 'api_token' => 'x', 'is_active' => true,
        ]);
    }

    private function cpanelServer(): Server
    {
        return Server::create([
            'name' => 'Hosting 1', 'panel' => 'cpanel', 'hostname' => 'h1.test', 'port' => 2087,
            'api_username' => 'root', 'api_token' => 'x', 'is_active' => true,
        ]);
    }

    public function test_produk_kategori_vps_tanpa_server_cloud_tetap_masuk_vps_bukan_hosting(): void
    {
        // Kasus persis yang dilaporkan: kategori sudah dibuat bertipe VPS,
        // tapi produknya sendiri belum/tidak dipasangi server cloud.
        $category = ProductGroup::create(['name' => 'Cloud VPS', 'type' => 'vps', 'is_active' => true]);
        $product = Product::create([
            'product_category_id' => $category->id, 'name' => 'Cloud VPS', 'domain_option' => 'none',
            'price_monthly' => 50000, 'is_active' => true, 'is_featured' => true,
        ]);

        $this->assertTrue($product->fresh()->isVpsProduct());
        $this->assertTrue(Product::vpsType()->whereKey($product->id)->exists());
        $this->assertFalse(Product::hostingType()->whereKey($product->id)->exists());
    }

    public function test_produk_kategori_hosting_di_server_cpanel_tetap_hosting(): void
    {
        $category = ProductGroup::create(['name' => 'Shared Hosting', 'type' => 'hosting', 'is_active' => true]);
        $product = Product::create([
            'product_category_id' => $category->id, 'name' => 'Hosting Pro', 'domain_option' => 'none',
            'server_id' => $this->cpanelServer()->id, 'price_monthly' => 20000, 'is_active' => true,
        ]);

        $this->assertFalse($product->fresh()->isVpsProduct());
        $this->assertTrue(Product::hostingType()->whereKey($product->id)->exists());
    }

    public function test_server_cloud_tetap_dianggap_vps_walau_kategori_belum_diisi_vps(): void
    {
        // Jaring pengaman untuk data lama.
        $category = ProductGroup::create(['name' => 'Belum Diklasifikasi', 'type' => 'hosting', 'is_active' => true]);
        $product = Product::create([
            'product_category_id' => $category->id, 'name' => 'VPS Lama', 'domain_option' => 'none',
            'server_id' => $this->cloudServer()->id, 'price_monthly' => 30000, 'is_active' => true,
        ]);

        $this->assertTrue($product->fresh()->isVpsProduct());
        $this->assertTrue(Product::vpsType()->whereKey($product->id)->exists());
    }
}
