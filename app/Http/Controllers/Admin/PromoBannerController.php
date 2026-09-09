<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromoBanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromoBannerController extends Controller
{
    public function index(): View
    {
        $banners = PromoBanner::orderBy('sort_order')->orderBy('id')->get();

        return view('admin.promo-banners.index', compact('banners'));
    }

    public function indexBootstrap(): View
    {
        $banners = PromoBanner::orderBy('sort_order')->orderBy('id')->get();

        return view('admin.promo-banners.index', compact('banners'));
    }

    public function create(): View
    {
        return view('admin.promo-banners.form', ['banner' => new PromoBanner()]);
    }

    public function createBootstrap(): View
    {
        return view('admin.promo-banners.form', ['banner' => new PromoBanner()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);
        $data['image'] = $this->storeImage($request);
        $data['sort_order'] = (int) PromoBanner::max('sort_order') + 1;
        $data['is_active'] = $request->boolean('is_active', true);
        $data['open_in_new_tab'] = $request->boolean('open_in_new_tab');

        PromoBanner::create($data);

        return redirect()->route('admin.promo-banners.index')->with('success', 'Banner promo berhasil ditambahkan.');
    }

    public function edit(PromoBanner $promoBanner): View
    {
        return view('admin.promo-banners.form', ['banner' => $promoBanner]);
    }

    public function editBootstrap(PromoBanner $promoBanner): View
    {
        return view('admin.promo-banners.form', ['banner' => $promoBanner]);
    }

    public function update(Request $request, PromoBanner $promoBanner): RedirectResponse
    {
        $data = $this->validated($request, false);

        if ($request->hasFile('image')) {
            $this->deleteImage($promoBanner->image);
            $data['image'] = $this->storeImage($request);
        }

        $data['is_active'] = $request->boolean('is_active');
        $data['open_in_new_tab'] = $request->boolean('open_in_new_tab');

        $promoBanner->update($data);

        return redirect()->route('admin.promo-banners.index')->with('success', 'Banner promo berhasil diperbarui.');
    }

    public function destroy(PromoBanner $promoBanner): RedirectResponse
    {
        $this->deleteImage($promoBanner->image);
        $promoBanner->delete();

        return back()->with('success', 'Banner promo berhasil dihapus.');
    }

    public function status(Request $request): RedirectResponse
    {
        $banner = PromoBanner::findOrFail($request->input('banner_id'));
        $banner->update(['is_active' => ! $banner->is_active]);

        return back()->with('success', 'Status banner berhasil diubah.');
    }

    public function move(Request $request, PromoBanner $promoBanner): RedirectResponse
    {
        $direction = $request->input('direction');

        $neighbor = $direction === 'up'
            ? PromoBanner::where('sort_order', '<', $promoBanner->sort_order)->orderByDesc('sort_order')->first()
            : PromoBanner::where('sort_order', '>', $promoBanner->sort_order)->orderBy('sort_order')->first();

        if (! $neighbor) {
            return back();
        }

        $a = $promoBanner->sort_order;
        $b = $neighbor->sort_order;
        $promoBanner->update(['sort_order' => $b]);
        $neighbor->update(['sort_order' => $a]);

        return back();
    }

    /**
     * Gambar banner disimpan lewat Storage disk 'local' (storage/app/
     * banners) dan dilayani lewat rute Laravel — bukan ditulis langsung
     * ke folder public/, karena di server dengan cPanel Git Version
     * Control (atau setup serupa), folder kode yang dieksekusi PHP itu
     * terpisah dari folder yang benar-benar dilayani ke publik.
     *
     * Gambar disimpan APA ADANYA (tanpa crop paksa) -- tampilan publik
     * (public._promo-banner-carousel*.blade.php) menampilkan gambar
     * sesuai rasio aslinya, jadi memaksa crop ke rasio tertentu di sini
     * cuma akan memotong bagian gambar (mis. tombol/teks di bawah)
     * tanpa bisa dikembalikan.
     */
    private function storeImage(Request $request): string
    {
        $filename = 'banner_' . time() . '_' . uniqid() . '.' . $request->file('image')->getClientOriginalExtension();

        $request->file('image')->storeAs('banners', $filename, 'local');

        return $filename;
    }

    private function deleteImage(?string $filename): void
    {
        if ($filename && \Illuminate\Support\Facades\Storage::disk('local')->exists('banners/' . $filename)) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete('banners/' . $filename);
        }
    }

    private function validated(Request $request, bool $imageRequired): array
    {
        return $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:500'],
            'image' => [$imageRequired ? 'required' : 'nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'link_url' => ['nullable', 'string', 'max:255'],
            'button_text' => ['nullable', 'string', 'max:50'],
            'open_in_new_tab' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'display_page' => ['required', 'in:' . implode(',', array_keys(\App\Models\PromoBanner::PAGES))],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ], [
            'image.required' => 'Unggah gambar banner.',
            'image.max' => 'Ukuran gambar maksimal 2 MB.',
        ]);
    }
}
