<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductCategoryController extends Controller
{
    public function index(): View
    {
        $categories = ProductCategory::withCount('products')->orderBy('sort_order')->orderBy('name')->paginate(15);

        return view('admin.product-categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin.product-categories.form', ['category' => new ProductCategory()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active', true);

        ProductCategory::create($data);

        return redirect()->route('admin.product-categories.index')->with('success', 'Kategori berhasil dibuat.');
    }

    public function edit(ProductCategory $productCategory): View
    {
        return view('admin.product-categories.form', ['category' => $productCategory]);
    }

    public function update(Request $request, ProductCategory $productCategory): RedirectResponse
    {
        $data = $this->validated($request, $productCategory->id);
        $data['is_active'] = $request->boolean('is_active');

        $productCategory->update($data);

        return redirect()->route('admin.product-categories.index')->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(ProductCategory $productCategory): RedirectResponse
    {
        if ($productCategory->products()->exists()) {
            return back()->with('error', 'Kategori tidak bisa dihapus karena masih punya produk. Pindahkan atau hapus produknya dulu.');
        }

        $productCategory->delete();

        return redirect()->route('admin.product-categories.index')->with('success', 'Kategori berhasil dihapus.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'type'        => ['required', 'in:hosting,vps'],
            'slug'        => ['nullable', 'string', 'max:255', 'unique:product_categories,slug' . ($ignoreId ? ",{$ignoreId}" : '')],
            'description' => ['nullable', 'string', 'max:500'],
            'icon'        => ['nullable', 'string', 'max:50'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['nullable', 'boolean'],
        ]);
    }
}
