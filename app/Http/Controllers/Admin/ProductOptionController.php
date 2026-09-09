<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductOptionController extends Controller
{
    public function index(Product $product): View
    {
        $product->load('optionGroups.options');

        return view('admin.products.options', compact('product'));
    }

    // ── Grup ─────────────────────────────────────────────────────────

    public function storeGroup(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'selection_type' => ['required', 'in:checkbox,radio'],
            'is_required'    => ['nullable', 'boolean'],
            'sort_order'     => ['nullable', 'integer', 'min:0'],
        ]);

        $data['is_required'] = $request->boolean('is_required');
        $data['product_id'] = $product->id;

        ProductOptionGroup::create($data);

        return back()->with('success', 'Grup opsi berhasil ditambahkan.');
    }

    public function updateGroup(Request $request, Product $product, ProductOptionGroup $group): RedirectResponse
    {
        $this->assertBelongsToProduct($product, $group);

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'selection_type' => ['required', 'in:checkbox,radio'],
            'is_required'    => ['nullable', 'boolean'],
            'sort_order'     => ['nullable', 'integer', 'min:0'],
        ]);

        $data['is_required'] = $request->boolean('is_required');

        $group->update($data);

        return back()->with('success', 'Grup opsi berhasil diperbarui.');
    }

    public function destroyGroup(Product $product, ProductOptionGroup $group): RedirectResponse
    {
        $this->assertBelongsToProduct($product, $group);

        // Hapus grup TIDAK menghapus opsi yang sudah pernah dipilih klien
        // di hosting_account_options -- itu snapshot independen (nama &
        // harga sudah disalin ke sana), jadi riwayat pesanan lama tetap
        // utuh meski katalog opsinya diubah/dihapus belakangan.
        $group->delete();

        return back()->with('success', 'Grup opsi berhasil dihapus.');
    }

    public function statusGroup(Product $product, ProductOptionGroup $group): RedirectResponse
    {
        $this->assertBelongsToProduct($product, $group);

        $group->update(['is_active' => ! $group->is_active]);

        return back()->with('success', "Grup \"{$group->name}\" berhasil " . ($group->is_active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    // ── Opsi ─────────────────────────────────────────────────────────

    public function storeOption(Request $request, Product $product, ProductOptionGroup $group): RedirectResponse
    {
        $this->assertBelongsToProduct($product, $group);

        $data = $this->validatedOption($request);
        $data['product_option_group_id'] = $group->id;

        ProductOption::create($data);

        return back()->with('success', 'Opsi berhasil ditambahkan.');
    }

    public function updateOption(Request $request, Product $product, ProductOptionGroup $group, ProductOption $option): RedirectResponse
    {
        $this->assertBelongsToProduct($product, $group);
        $this->assertOptionBelongsToGroup($group, $option);

        $option->update($this->validatedOption($request));

        return back()->with('success', 'Opsi berhasil diperbarui.');
    }

    public function destroyOption(Product $product, ProductOptionGroup $group, ProductOption $option): RedirectResponse
    {
        $this->assertBelongsToProduct($product, $group);
        $this->assertOptionBelongsToGroup($group, $option);

        $option->delete();

        return back()->with('success', 'Opsi berhasil dihapus.');
    }

    public function statusOption(Product $product, ProductOptionGroup $group, ProductOption $option): RedirectResponse
    {
        $this->assertBelongsToProduct($product, $group);
        $this->assertOptionBelongsToGroup($group, $option);

        $option->update(['is_active' => ! $option->is_active]);

        return back()->with('success', "Opsi \"{$option->name}\" berhasil " . ($option->is_active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    private function validatedOption(Request $request): array
    {
        $data = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'price_monthly'       => ['nullable', 'numeric', 'min:0'],
            'price_quarterly'     => ['nullable', 'numeric', 'min:0'],
            'price_semi_annually' => ['nullable', 'numeric', 'min:0'],
            'price_annually'      => ['nullable', 'numeric', 'min:0'],
            'price_custom'        => ['nullable', 'numeric', 'min:0'],
            'sort_order'          => ['nullable', 'integer', 'min:0'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    /**
     * Route model binding tidak otomatis memastikan $group ini benar milik
     * $product (cuma mencocokkan ID grup, bukan relasinya) -- tanpa cek
     * ini, admin bisa saja menebak-nebak ID grup produk lain lewat URL.
     */
    private function assertBelongsToProduct(Product $product, ProductOptionGroup $group): void
    {
        abort_unless($group->product_id === $product->id, 404);
    }

    private function assertOptionBelongsToGroup(ProductOptionGroup $group, ProductOption $option): void
    {
        abort_unless($option->product_option_group_id === $group->id, 404);
    }
}
