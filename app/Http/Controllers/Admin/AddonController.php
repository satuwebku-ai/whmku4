<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Addon;
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
        $data = $this->validated($request);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['is_active'] = $request->boolean('is_active', true);

        Addon::create($data);

        return redirect()->route('admin.addons.index')->with('success', 'Addon berhasil dibuat.');
    }

    public function edit(Addon $addon): View
    {
        return view('admin.addons.form', compact('addon'));
    }

    public function update(Request $request, Addon $addon): RedirectResponse
    {
        $data = $this->validated($request, $addon->id);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['is_active'] = $request->boolean('is_active');

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

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'                 => ['required', 'string', 'max:255'],
            'slug'                 => ['nullable', 'string', 'max:255', 'unique:addons,slug' . ($ignoreId ? ",{$ignoreId}" : '')],
            'description'          => ['nullable', 'string', 'max:1000'],
            'price_monthly'        => ['nullable', 'numeric', 'min:0'],
            'price_quarterly'      => ['nullable', 'numeric', 'min:0'],
            'price_semi_annually'  => ['nullable', 'numeric', 'min:0'],
            'price_annually'       => ['nullable', 'numeric', 'min:0'],
            'sort_order'           => ['nullable', 'integer', 'min:0'],
            'is_active'            => ['nullable', 'boolean'],
        ]);
    }
}
