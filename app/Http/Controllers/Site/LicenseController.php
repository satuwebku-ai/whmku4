<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LicenseController extends Controller
{
    public function index(Request $request): View
    {
        $all = Addon::query()
            ->active()
            ->licenses()
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (Addon $addon) => $addon->availableCycles() !== [])
            ->values();

        // Kategori yang benar-benar punya produk saja yang jadi tab filter.
        $categories = collect(Addon::CATEGORIES)
            ->map(fn (string $label, string $key) => ['label' => $label, 'count' => $all->where('category', $key)->count()])
            ->filter(fn (array $c) => $c['count'] > 0);

        $activeCategory = $categories->has($request->query('kategori')) ? $request->query('kategori') : null;
        $licenses = $activeCategory ? $all->where('category', $activeCategory)->values() : $all;

        return view('public.licenses.index', compact('licenses', 'categories', 'activeCategory', 'all'));
    }

    public function show(string $slug): View
    {
        $license = Addon::query()
            ->active()
            ->licenses()
            ->where('is_public', true)
            ->where('slug', $slug)
            ->firstOrFail();

        abort_if($license->availableCycles() === [], 404);

        $related = Addon::query()
            ->active()
            ->licenses()
            ->where('is_public', true)
            ->where('category', $license->category)
            ->whereKeyNot($license->id)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Addon $addon) => $addon->availableCycles() !== [])
            ->take(3)
            ->values();

        // Variabel tampilan disiapkan di sini (bukan di blok @php pada view)
        // supaya halaman detail tidak bergantung pada urutan direktif Blade.
        return view('public.licenses.show', [
            'license' => $license,
            'related' => $related,
            'cycles' => $license->availableCycles(),
            'isSsl' => $license->category === 'ssl',
            'labels' => Addon::CYCLE_LABELS,
            'suffix' => Addon::CYCLE_SUFFIX,
        ]);
    }
}
