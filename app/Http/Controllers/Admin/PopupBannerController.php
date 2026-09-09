<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Banner POPUP (modal) di halaman depan publik -- beda dari Banner
 * Promo (carousel inline). Sengaja cuma SATU banner (bukan banyak
 * seperti carousel), karena beberapa modal menumpuk sekaligus akan
 * jadi pengalaman yang buruk buat pengunjung.
 */
class PopupBannerController extends Controller
{
    public function edit(): View
    {
        return view('admin.popup-banner.edit');
    }

    public function editBootstrap(): View
    {
        return view('admin.popup-banner.edit');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'popup_banner_title'       => ['nullable', 'string', 'max:150'],
            'popup_banner_description' => ['nullable', 'string', 'max:500'],
            'popup_banner_button_text' => ['nullable', 'string', 'max:50'],
            'popup_banner_link_url'    => ['nullable', 'string', 'max:255'],
            'popup_banner_frequency'   => ['required', 'in:every_visit,once_per_session,once_per_day'],
            'popup_banner_image'       => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ], [
            'popup_banner_image.max' => 'Ukuran gambar maksimal 2 MB.',
        ]);

        $data['popup_banner_enabled'] = $request->boolean('popup_banner_enabled') ? '1' : '0';

        unset($data['popup_banner_image']);

        if ($request->hasFile('popup_banner_image')) {
            $old = Setting::get('popup_banner_image');

            if ($old && Storage::disk('local')->exists('branding/' . $old)) {
                Storage::disk('local')->delete('branding/' . $old);
            }

            $filename = 'popup_banner_' . time() . '.' . $request->file('popup_banner_image')->getClientOriginalExtension();
            Storage::disk('local')->makeDirectory('branding');

            // Gambar disimpan apa adanya (tanpa crop paksa). Tampilan
            // publik (public.partials.popup-banner*.blade.php) sudah
            // pakai object-fit:contain dengan tinggi maksimum, jadi
            // gambar apa pun rasionya akan tampil utuh tanpa terpotong.
            $request->file('popup_banner_image')->storeAs('branding', $filename, 'local');

            $data['popup_banner_image'] = $filename;
        }

        if ($request->boolean('remove_popup_banner_image')) {
            $old = Setting::get('popup_banner_image');

            if ($old && Storage::disk('local')->exists('branding/' . $old)) {
                Storage::disk('local')->delete('branding/' . $old);
            }

            $data['popup_banner_image'] = null;
        }

        Setting::putMany($data, 'general');

        return back()->with('success', 'Banner popup berhasil disimpan.');
    }
}
