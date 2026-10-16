<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ProductType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductTypeController extends Controller
{
    public function index(): View
    {
        $types = ProductType::withCount('categories')->orderBy('sort_order')->orderBy('name')->paginate(15);

        return view('admin.product-types.index', compact('types'));
    }

    public function create(): View
    {
        return view('admin.product-types.form', ['type' => new ProductType(['kind' => 'hosting', 'color' => '#4f46e5', 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? null, $data['name']);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['color'] = $data['color'] ?? '#4f46e5';

        $type = ProductType::create($data);

        $this->audit('Jenis produk dibuat', "{$type->name} (perilaku: {$type->kind})", 'info');

        return redirect()->route('admin.product-types.index')->with('success', 'Jenis produk berhasil dibuat.');
    }

    public function edit(ProductType $productType): View
    {
        return view('admin.product-types.form', ['type' => $productType->loadCount('categories')]);
    }

    public function update(Request $request, ProductType $productType): RedirectResponse
    {
        $data = $this->validated($request, $productType->id);

        // Perilaku (hosting/vps) menentukan aturan server & tagihan semua
        // produk di kategori yang memakai jenis ini. Produk lama tidak
        // dicek ulang, jadi perilaku dikunci selama masih ada kategorinya.
        if ($data['kind'] !== $productType->kind && $productType->categories()->exists()) {
            return back()->withInput()->withErrors(['kind' => 'Perilaku tidak bisa diubah selama masih ada kategori yang memakai jenis ini. Pindahkan kategorinya ke jenis lain dulu.']);
        }

        $data['slug'] = $this->uniqueSlug($data['slug'] ?? null, $data['name'], $productType->id);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['color'] = $data['color'] ?? '#4f46e5';

        $productType->update($data);

        $this->audit('Jenis produk diubah', $productType->name, 'info');

        return redirect()->route('admin.product-types.index')->with('success', 'Jenis produk berhasil diperbarui.');
    }

    public function destroy(ProductType $productType): RedirectResponse
    {
        if ($productType->categories()->exists()) {
            return back()->with('error', 'Jenis tidak bisa dihapus karena masih dipakai kategori produk. Pindahkan kategorinya ke jenis lain dulu.');
        }

        $name = $productType->name;
        $productType->delete();

        $this->audit('Jenis produk dihapus', $name, 'warning');

        return redirect()->route('admin.product-types.index')->with('success', 'Jenis produk berhasil dihapus.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'slug'        => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('product_types', 'slug')->ignore($ignoreId)],
            'kind'        => ['required', Rule::in(array_keys(ProductType::KINDS))],
            'icon'        => ['nullable', 'string', 'max:50'],
            'color'       => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['nullable', 'boolean'],
        ], [
            'slug.regex'  => 'Slug hanya boleh huruf kecil, angka, dan tanda hubung.',
            'color.regex' => 'Warna harus berformat hex, mis. #4f46e5.',
        ]);
    }

    private function uniqueSlug(?string $slug, string $name, ?int $ignoreId = null): string
    {
        $base = filled($slug) ? $slug : (Str::slug($name) ?: 'jenis');
        $candidate = $base;
        $i = 2;

        while (ProductType::where('slug', $candidate)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $candidate = $base . '-' . $i++;
        }

        return $candidate;
    }

    private function audit(string $title, string $detail, string $level): void
    {
        $who = auth('admin')->user()->name ?? 'admin';

        ActivityLog::record('service', $title, "{$detail}. Oleh {$who}.", null, $level);
    }
}
