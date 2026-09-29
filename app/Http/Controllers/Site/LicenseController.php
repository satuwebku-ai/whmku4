<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use Illuminate\View\View;

class LicenseController extends Controller
{
    public function index(): View
    {
        $licenses = Addon::query()
            ->active()
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (Addon $addon) => $addon->availableCycles() !== [])
            ->values();

        return view('public.licenses.index', compact('licenses'));
    }

    public function show(string $slug): View
    {
        $license = Addon::query()
            ->active()
            ->where('is_public', true)
            ->where('slug', $slug)
            ->firstOrFail();

        abort_if($license->availableCycles() === [], 404);

        return view('public.licenses.show', compact('license'));
    }
}