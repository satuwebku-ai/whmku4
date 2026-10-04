<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Services\SupplierPricingSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AddonController extends Controller
{
    public function index(): View
    {
        $addons = Addon::withCount('attachments')->orderBy('sort_order')->orderBy('name')->paginate(15);

        return view('admin.addons.index', compact('addons'));
    }

    public function create(): View
    {
        return view('admin.addons.form', ['addon' => new Addon()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->withCatalogDetails($this->validated($request));
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['is_public'] = $request->boolean('is_public', true);

        Addon::create($data);

        return redirect()->route('admin.addons.index')->with('success', 'Addon berhasil dibuat.');
    }

    public function edit(Addon $addon): View
    {
        return view('admin.addons.form', compact('addon'));
    }

    public function update(Request $request, Addon $addon): RedirectResponse
    {
        $data = $this->withCatalogDetails($this->validated($request, $addon->id));
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['is_active'] = $request->boolean('is_active');
        $data['is_public'] = $request->boolean('is_public');

        // Token terenkripsi yang sudah tersimpan tidak boleh terhapus hanya
        // karena admin mengosongkan input password pada form edit.
        if (! $request->filled('supplier_api_token')) {
            unset($data['supplier_api_token']);
        }

        $addon->update($data);

        return redirect()->route('admin.addons.index')->with('success', 'Addon berhasil diperbarui.');
    }

    public function destroy(Addon $addon): RedirectResponse
    {
        if ($addon->attachments()->where('status', 'active')->exists()) {
            return back()->with('error', 'Addon tidak bisa dihapus karena masih dipakai aktif oleh layanan klien. Nonaktifkan saja supaya tidak bisa dipesan baru.');
        }

        $addon->delete();

        return redirect()->route('admin.addons.index')->with('success', 'Addon berhasil dihapus.');
    }

    public function status(Request $request): RedirectResponse
    {
        $addon = Addon::findOrFail($request->input('addon_id'));
        $addon->update(['is_active' => ! $addon->is_active]);

        return back()->with('success', "Addon {$addon->name} berhasil " . ($addon->is_active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    public function sync(Addon $addon, SupplierPricingSyncService $syncer): RedirectResponse
    {
        if (! $addon->isApiPricing()) {
            return back()->with('error', 'Addon ini masih memakai harga modal manual.');
        }

        try {
            $result = $syncer->sync($addon);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $result['message']);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'                 => ['required', 'string', 'max:255'],
            'slug'                 => ['nullable', 'string', 'max:255', 'unique:addons,slug' . ($ignoreId ? ",{$ignoreId}" : '')],
            'description'          => ['nullable', 'string', 'max:1000'],
            'category'             => ['required', 'in:' . implode(',', array_keys(Addon::allCategories()))],
            'brand'                => ['nullable', 'string', 'max:100'],
            'summary'              => ['nullable', 'string', 'max:255'],
            'long_description'     => ['nullable', 'string', 'max:10000'],
            'features_text'        => ['nullable', 'string', 'max:5000'],
            'specs_text'           => ['nullable', 'string', 'max:5000'],
            'faqs_text'            => ['nullable', 'string', 'max:10000'],
            'price_monthly'        => ['nullable', 'numeric', 'min:0'],
            'price_quarterly'      => ['nullable', 'numeric', 'min:0'],
            'price_semi_annually'  => ['nullable', 'numeric', 'min:0'],
            'price_annually'       => ['nullable', 'numeric', 'min:0'],
            'cost_price_monthly'       => ['nullable', 'numeric', 'min:0'],
            'cost_price_quarterly'     => ['nullable', 'numeric', 'min:0'],
            'cost_price_semi_annually' => ['nullable', 'numeric', 'min:0'],
            'cost_price_annually'      => ['nullable', 'numeric', 'min:0'],
            'pricing_source'           => ['required', 'in:manual,api'],
            'is_public'                => ['nullable', 'boolean'],
            'supplier_api_url'         => ['nullable', 'url', 'max:1000', function ($attribute, $value, $fail) {
                if (filled($value) && ! \App\Support\UrlGuard::isPublicHttpUrl($value)) {
                    $fail('URL API supplier harus mengarah ke alamat publik (bukan localhost/jaringan internal).');
                }
            }],
            'supplier_http_method'     => ['nullable', 'in:GET,POST'],
            'supplier_api_token'       => ['nullable', 'string', 'max:5000'],
            'supplier_price_path_monthly'       => ['nullable', 'string', 'max:255'],
            'supplier_price_path_quarterly'     => ['nullable', 'string', 'max:255'],
            'supplier_price_path_semi_annually' => ['nullable', 'string', 'max:255'],
            'supplier_price_path_annually'      => ['nullable', 'string', 'max:255'],
            'sort_order'           => ['nullable', 'integer', 'min:0'],
            'is_active'            => ['nullable', 'boolean'],
        ]);
    }

    /**
     * Ubah isian teks form (satu baris per item) menjadi kolom JSON:
     *  - fitur      : satu fitur per baris
     *  - spesifikasi: "Label: nilai" per baris
     *  - FAQ        : "Pertanyaan | Jawaban" per baris
     */
    private function withCatalogDetails(array $data): array
    {
        $lines = fn (?string $text) => collect(preg_split('/\R/', (string) $text))
            ->map(fn ($l) => trim($l))->filter()->values();

        $data['features'] = $lines($data['features_text'] ?? null)->all() ?: null;

        $data['specs'] = $lines($data['specs_text'] ?? null)
            ->mapWithKeys(function ($l) {
                [$k, $v] = array_pad(explode(':', $l, 2), 2, '');

                return trim($k) !== '' && trim($v) !== '' ? [trim($k) => trim($v)] : [];
            })->all() ?: null;

        $data['faqs'] = $lines($data['faqs_text'] ?? null)
            ->map(function ($l) {
                [$q, $a] = array_pad(explode('|', $l, 2), 2, '');

                return trim($q) !== '' && trim($a) !== '' ? ['q' => trim($q), 'a' => trim($a)] : null;
            })->filter()->values()->all() ?: null;

        unset($data['features_text'], $data['specs_text'], $data['faqs_text']);

        return $data;
    }
}
