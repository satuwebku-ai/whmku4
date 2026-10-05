<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ProductGroup;
use App\Models\Server;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductCategoryController extends Controller
{
    public function index(): View
    {
        $categories = ProductGroup::withCount('products')->orderBy('sort_order')->orderBy('name')->paginate(15);

        return view('admin.product-categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin.product-categories.form', ['category' => new ProductGroup()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active', true);

        $category = ProductGroup::create($data);

        $this->audit('Kategori produk dibuat', "{$category->name} (jenis: {$category->type})", 'info');

        return redirect()->route('admin.product-categories.index')->with('success', 'Kategori berhasil dibuat.');
    }

    public function edit(ProductGroup $productCategory): View
    {
        return view('admin.product-categories.form', ['category' => $productCategory]);
    }

    public function update(Request $request, ProductGroup $productCategory): RedirectResponse
    {
        $data = $this->validated($request, $productCategory->id);
        $data['is_active'] = $request->boolean('is_active');

        // Mengubah jenis (hosting <-> vps) mengubah URL publik (/hosting/...
        // <-> /vps/...) DAN aturan server tiap produknya. Produk lama tidak
        // dicek ulang oleh ProductController, jadi tanpa guard ini produk
        // hosting bisa tiba-tiba "berubah" jadi produk VPS tanpa server
        // cloud (atau sebaliknya) dan nyasar di katalog yang salah.
        if ($data['type'] !== ($productCategory->type ?? 'hosting')) {
            $conflict = $this->typeChangeConflict($productCategory, $data['type']);

            if ($conflict) {
                return back()->withInput()->withErrors(['type' => $conflict]);
            }
        }

        $productCategory->fill($data);
        $changes = collect($productCategory->getDirty())
            ->map(fn ($new, $field) => "{$field}: " . json_encode($productCategory->getOriginal($field)) . ' → ' . json_encode($new))
            ->implode('; ');

        $productCategory->save();

        if ($changes !== '') {
            $this->audit('Kategori produk diubah', "{$productCategory->name} — {$changes}", 'info');
        }

        return redirect()->route('admin.product-categories.index')->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(ProductGroup $productCategory): RedirectResponse
    {
        if ($productCategory->products()->exists()) {
            return back()->with('error', 'Kategori tidak bisa dihapus karena masih punya produk. Pindahkan atau hapus produknya dulu.');
        }

        $name = $productCategory->name;
        $productCategory->delete();

        $this->audit('Kategori produk dihapus', $name, 'warning');

        return redirect()->route('admin.product-categories.index')->with('success', 'Kategori berhasil dihapus.');
    }

    /**
     * Pesan error kalau perubahan jenis kategori bertabrakan dengan server
     * produk di dalamnya; null kalau aman. Aturannya sama dengan
     * ProductController::assertCategoryMatchesServer().
     */
    private function typeChangeConflict(ProductGroup $category, string $newType): ?string
    {
        $cloudIds = Server::cloud()->pluck('id');

        $bad = $category->products()
            ->when($newType === 'vps',
                fn ($q) => $q->where(fn ($w) => $w->whereNull('server_id')->orWhereNotIn('server_id', $cloudIds)),
                fn ($q) => $q->whereIn('server_id', $cloudIds))
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($bad->isEmpty()) {
            return null;
        }

        $names = $bad->take(5)->pluck('name')->implode(', ') . ($bad->count() > 5 ? ', …' : '');
        $need = $newType === 'vps' ? 'belum memakai server VPS/cloud' : 'memakai server VPS/cloud';

        return "Jenis tidak bisa diubah ke " . strtoupper($newType) . " karena {$bad->count()} produk di kategori ini {$need}: {$names}. Pindahkan produk tersebut ke kategori lain atau sesuaikan servernya dulu.";
    }

    private function audit(string $title, string $detail, string $level): void
    {
        $who = auth('admin')->user()->name ?? 'admin';

        ActivityLog::record('service', $title, "{$detail}. Oleh {$who}.", null, $level);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'type'        => ['required', 'in:hosting,vps'],
            'slug'        => ['nullable', 'string', 'max:255', 'unique:product_groups,slug' . ($ignoreId ? ",{$ignoreId}" : '')],
            'description' => ['nullable', 'string', 'max:500'],
            'icon'        => ['nullable', 'string', 'max:50'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['nullable', 'boolean'],
        ]);
    }
}
