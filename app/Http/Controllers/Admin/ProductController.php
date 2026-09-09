<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Server;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.products.index', $this->indexData($request));
    }

    public function indexBootstrap(Request $request): View
    {
        return view('admin.products.index', $this->indexData($request));
    }

    private function indexData(Request $request): array
    {
        // Produk VPS dibedakan dari produk hosting biasa lewat jenis
        // SERVER tujuannya (cloud provider vs cPanel/WHM) -- tidak perlu
        // kolom "tipe" baru di database, karena informasinya sudah ada.
        $cloudServerIds = Server::whereIn('panel', ['idcloudhost'])->pluck('id');

        $products = Product::with('category')
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->category_id, fn ($q) => $q->where('product_category_id', $request->category_id))
            ->when($request->type === 'vps', fn ($q) => $q->whereIn('server_id', $cloudServerIds))
            ->when($request->type === 'hosting', fn ($q) => $q->where(fn ($w) => $w->whereNotIn('server_id', $cloudServerIds)->orWhereNull('server_id')))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $categories = ProductCategory::orderBy('name')->get();

        $counts = [
            'all'     => Product::count(),
            'vps'     => Product::whereIn('server_id', $cloudServerIds)->count(),
            'hosting' => Product::where(fn ($w) => $w->whereNotIn('server_id', $cloudServerIds)->orWhereNull('server_id'))->count(),
        ];

        return compact('products', 'categories', 'counts', 'cloudServerIds');
    }

    public function create(): View
    {
        return view('admin.products.form', [
            'product' => new Product(),
            'categories' => ProductCategory::orderBy('name')->get(),
            'servers' => Server::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function createBootstrap(): View
    {
        return view('admin.products.form', [
            'product' => new Product(),
            'categories' => ProductCategory::orderBy('name')->get(),
            'servers' => Server::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $this->assertHasPrice($data);
        $data = $this->withExtras($request, $data);

        Product::create($data);

        return redirect()->route('admin.products.index')->with('success', 'Produk berhasil dibuat.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.form', [
            'product' => $product,
            'categories' => ProductCategory::orderBy('name')->get(),
            'servers' => Server::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function editBootstrap(Product $product): View
    {
        return view('admin.products.form', [
            'product' => $product,
            'categories' => ProductCategory::orderBy('name')->get(),
            'servers' => Server::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request, $product->id);
        $this->assertHasPrice($data);
        $data = $this->withExtras($request, $data);

        $product->update($data);

        return redirect()->route('admin.products.index')->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Produk berhasil dihapus.');
    }

    public function status(Request $request): RedirectResponse
    {
        $product = Product::findOrFail($request->input('product_id'));
        $product->update(['is_active' => ! $product->is_active]);

        return back()->with('success', "Produk {$product->name} berhasil " . ($product->is_active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    /**
     * Produk tanpa satupun harga siklus terisi tidak bisa dibeli — tolak
     * sebelum tersimpan, bukan biarkan muncul rusak di katalog.
     */
    private function assertHasPrice(array $data): void
    {
        $hasPrice = collect(['price_monthly', 'price_quarterly', 'price_semi_annually', 'price_annually', 'price_custom'])
            ->contains(fn ($key) => filled($data[$key] ?? null));

        if (! $hasPrice) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'price_monthly' => 'Isi minimal satu harga siklus tagihan (bulanan/3 bulan/6 bulan/tahunan).',
            ]);
        }
    }

    private function withExtras(Request $request, array $data): array
    {
        $data['is_active'] = $request->boolean('is_active', true);
        $data['is_featured'] = $request->boolean('is_featured');

        // Jumlah hari siklus custom CUMA boleh diubah Superadmin -- dijaga
        // di sini juga (bukan cuma disembunyikan di tampilan), supaya
        // tidak bisa diakali admin/staff biasa lewat request langsung.
        if (! auth('admin')->user()->isSuperadmin()) {
            unset($data['custom_cycle_days']);
        }

        // Textarea satu fitur per baris -> array bersih (baris kosong dibuang).
        $features = collect(explode("\n", (string) $request->input('features_raw')))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();

        $data['features'] = $features;

        return $data;
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'product_category_id' => ['required', 'exists:product_categories,id'],
            'name'                => ['required', 'string', 'max:255'],
            'slug'                => ['nullable', 'string', 'max:255', 'unique:products,slug' . ($ignoreId ? ",{$ignoreId}" : '')],
            'tagline'             => ['nullable', 'string', 'max:255'],
            'description'         => ['nullable', 'string'],
            'price_monthly'       => ['nullable', 'numeric', 'min:0'],
            'price_quarterly'     => ['nullable', 'numeric', 'min:0'],
            'price_semi_annually' => ['nullable', 'numeric', 'min:0'],
            'price_annually'      => ['nullable', 'numeric', 'min:0'],
            'price_custom'        => ['nullable', 'numeric', 'min:0'],
            'custom_cycle_days'   => ['nullable', 'integer', 'min:1', 'max:3650'],
            'setup_fee'           => ['nullable', 'numeric', 'min:0'],
            'domain_option'       => ['required', 'in:required,optional,none'],
            'server_id'           => ['nullable', 'exists:servers,id'],
            'panel_package'       => ['nullable', 'string', 'max:500'],
            'billing_mode'        => ['nullable', 'in:invoice,deposit'],
            'stock'               => ['nullable', 'integer', 'min:0'],
            'sort_order'          => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
