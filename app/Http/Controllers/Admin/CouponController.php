<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CouponController extends Controller
{
    public function coupons(Request $request): View
    {
        return view('admin.coupons.index', $this->indexData($request));
    }

    public function couponsBootstrap(Request $request): View
    {
        return view('admin.coupons.index', $this->indexData($request));
    }

    private function indexData(Request $request): array
    {
        $coupons = Coupon::query()
            ->when($request->search, fn ($q) => $q->where('code', 'like', '%' . strtoupper($request->search) . '%'))
            ->withCount('invoices')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return ['coupons' => $coupons];
    }

    public function create(): View
    {
        return view('admin.coupons.form', [
            'coupon' => new Coupon(),
            'categories' => ProductCategory::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
        ]);
    }

    public function createBootstrap(): View
    {
        return view('admin.coupons.form', [
            'coupon' => new Coupon(),
            'categories' => ProductCategory::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active', true);

        $coupon = Coupon::create($data);
        $this->syncScope($request, $coupon);

        return redirect()->route('admin.coupons')->with('success', 'Kupon berhasil dibuat.');
    }

    public function edit(Coupon $coupon): View
    {
        $coupon->load('products', 'categories');

        return view('admin.coupons.form', [
            'coupon' => $coupon,
            'categories' => ProductCategory::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
        ]);
    }

    public function editBootstrap(Coupon $coupon): View
    {
        $coupon->load('products', 'categories');

        return view('admin.coupons.form', [
            'coupon' => $coupon,
            'categories' => ProductCategory::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $data = $this->validated($request, $coupon->id);
        $data['is_active'] = $request->boolean('is_active');

        $coupon->update($data);
        $this->syncScope($request, $coupon);

        return redirect()->route('admin.coupons')->with('success', 'Kupon berhasil diperbarui.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        if ($coupon->invoices()->exists()) {
            return back()->with('error', 'Kupon tidak bisa dihapus karena sudah pernah dipakai. Nonaktifkan saja.');
        }

        $coupon->delete();

        return redirect()->route('admin.coupons')->with('success', 'Kupon berhasil dihapus.');
    }

    public function status(Request $request): RedirectResponse
    {
        $coupon = Coupon::findOrFail($request->input('coupon_id'));
        $coupon->update(['is_active' => ! $coupon->is_active]);

        return back()->with('success', "Kupon {$coupon->code} berhasil " . ($coupon->is_active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    /**
     * Simpan pilihan kategori/produk sasaran kupon. Kalau applies_to
     * bukan "specific", pivot-nya dikosongkan — supaya tidak ada sisa
     * pilihan lama yang mengambang kalau admin ganti pikiran balik ke
     * "Semua Produk".
     */
    private function syncScope(Request $request, Coupon $coupon): void
    {
        if ($coupon->applies_to !== 'specific') {
            $coupon->products()->sync([]);
            $coupon->categories()->sync([]);

            return;
        }

        $coupon->products()->sync($request->input('product_ids', []));
        $coupon->categories()->sync($request->input('category_ids', []));
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'code'                    => ['required', 'string', 'max:50', 'unique:coupons,code' . ($ignoreId ? ",{$ignoreId}" : '')],
            'type'                    => ['required', 'in:percent,fixed'],
            'value'                   => ['required', 'numeric', 'min:0.01'],
            'applies_to'              => ['required', 'in:all,specific'],
            'product_ids'             => ['nullable', 'array'],
            'product_ids.*'           => ['exists:products,id'],
            'category_ids'            => ['nullable', 'array'],
            'category_ids.*'          => ['exists:product_categories,id'],
            'min_order'               => ['nullable', 'numeric', 'min:0'],
            'max_discount'            => ['nullable', 'numeric', 'min:0'],
            'usage_limit'             => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_client'  => ['required', 'integer', 'min:1'],
            'starts_at'               => ['nullable', 'date'],
            'expires_at'              => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active'               => ['nullable', 'boolean'],
        ], [
            'value.min' => 'Nilai kupon harus lebih dari 0.',
        ]);

        if ($data['applies_to'] === 'specific' && empty($data['product_ids']) && empty($data['category_ids'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'scope' => 'Pilih minimal satu kategori atau produk untuk kupon "Produk Tertentu".',
            ]);
        }

        // product_ids/category_ids bukan kolom di tabel coupons — disimpan
        // terpisah lewat syncScope() ke tabel pivot, bukan ikut mass-assign.
        unset($data['product_ids'], $data['category_ids']);

        return $data;
    }
}
