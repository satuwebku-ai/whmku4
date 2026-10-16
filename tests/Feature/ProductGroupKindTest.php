<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Perilaku VPS/hosting sebuah kategori dibaca dari product_types.kind (data admin),
 * bukan dari kolom type lama dan bukan dari teks slug.
 */
class ProductGroupKindTest extends TestCase
{
    use RefreshDatabase;

    private function type(string $slug, string $kind): ProductType
    {
        return ProductType::create(['slug' => $slug, 'name' => ucfirst($slug), 'kind' => $kind, 'is_active' => true]);
    }

    private function group(string $name, ?ProductType $type): ProductGroup
    {
        return ProductGroup::create(['name' => $name, 'product_type_id' => $type?->id, 'is_active' => true]);
    }

    public function test_behaviour_follows_the_kind_even_when_the_slug_says_otherwise(): void
    {
        $cloud = $this->group('Cloud X', $this->type('cloudx', 'vps'));      // slug bebas, kind vps
        $fake = $this->group('Bukan VPS', $this->type('vps-palsu', 'hosting')); // slug "vps", kind hosting

        $this->assertTrue($cloud->fresh()->isVps());
        $this->assertFalse($fake->fresh()->isVps());
        $this->assertSame('vps', ProductType::kindOfSlug('cloudx'));
        $this->assertSame('hosting', ProductType::kindOfSlug('vps-palsu'));
        $this->assertNull(ProductType::kindOfSlug('tidak-ada'));
    }

    public function test_of_kind_and_not_of_kind_scopes(): void
    {
        $hosting = $this->group('H', ProductType::where('slug', 'hosting')->first());
        $vps = $this->group('V', $this->type('cloudx', 'vps'));

        $this->assertSame([$vps->id], ProductGroup::ofKind('vps')->pluck('id')->all());
        $this->assertSame([$hosting->id], ProductGroup::notOfKind('vps')->pluck('id')->all());
        $this->assertSame([$hosting->id], ProductGroup::ofKind('hosting')->pluck('id')->all());
    }

    public function test_products_in_a_vps_kind_category_are_classified_as_vps(): void
    {
        $group = $this->group('V', $this->type('cloudx', 'vps'));
        $product = Product::create([
            'product_category_id' => $group->id, 'name' => 'VPS X', 'domain_option' => 'none',
            'price_monthly' => 50000, 'is_active' => true,
        ]);

        $this->assertTrue($product->fresh()->isVpsProduct());
        $this->assertTrue(Product::vpsType()->whereKey($product->id)->exists());
        $this->assertFalse(Product::hostingType()->whereKey($product->id)->exists());
    }

    public function test_a_group_created_without_a_type_gets_the_first_hosting_type(): void
    {
        $group = ProductGroup::create(['name' => 'Tanpa Jenis', 'is_active' => true]);

        $this->assertSame('hosting', $group->fresh()->productType?->kind);
        $this->assertFalse($group->fresh()->isVps());
    }

    public function test_the_legacy_type_column_is_no_longer_created(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('product_groups', 'type'));
    }
}
