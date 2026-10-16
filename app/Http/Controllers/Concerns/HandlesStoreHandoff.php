<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Middleware\RequirePortalOrigin;
use App\Support\CartNotice;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Perilaku bersama untuk aksi "pilih -> masuk keranjang" yang bisa dikirim dari
 * dua tempat: halaman toko di domain PUBLIK (lintas domain) dan halaman di
 * domain client sendiri.
 *
 * Kalau permintaan datang dari situs publik, `back()` mengembalikan pengunjung
 * ke halaman publik, tapi pesan flash/galat tersimpan di sesi CLIENT sehingga
 * tidak akan terlihat di sana. Karena itu kegagalan dari situs publik dibawa
 * kembali ke halaman publik asal sebagai pesan terenkripsi (App\Support\CartNotice,
 * dibuka RestoreCartNotice), lengkap dengan isian form -- atau ditampilkan di
 * keranjang bila halaman asal tidak diketahui. Dari domain client sendiri
 * perilakunya tetap seperti biasa.
 */
trait HandlesStoreHandoff
{
    protected function fromPublicSite(Request $request): bool
    {
        return RequirePortalOrigin::fromPublicSite($request);
    }

    /**
     * Validasi input. Dari situs publik, kegagalan dikembalikan ke halaman publik
     * asal ($backUrl) dengan pesan galat dan isian form (CartNotice), atau ke
     * keranjang bila alamat asal tidak diketahui; selain itu memakai perilaku
     * validasi biasa.
     *
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     * @param  string|\Closure|null  $backUrl  alamat halaman PUBLIK asal (Closure = dihitung hanya bila perlu)
     * @return array<string, mixed>
     */
    protected function validateHandoff(Request $request, array $rules, array $messages = [], string|\Closure|null $backUrl = null): array
    {
        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails() && $this->fromPublicSite($request)) {
            throw new HttpResponseException(
                $this->failureToPublicSite($request, (string) $validator->errors()->first(), $backUrl)
            );
        }

        return $validator->validate();
    }

    /**
     * Respons gagal untuk aksi tambah-ke-keranjang.
     *
     * @param  string|\Closure|null  $backUrl  alamat halaman PUBLIK asal
     */
    protected function handoffFailure(Request $request, string $message, string|\Closure|null $backUrl = null): RedirectResponse
    {
        if ($this->fromPublicSite($request)) {
            return $this->failureToPublicSite($request, $message, $backUrl);
        }

        return back()->withInput()->with('error', $message);
    }

    private function failureToPublicSite(Request $request, string $message, string|\Closure|null $backUrl): RedirectResponse
    {
        $url = $backUrl instanceof \Closure ? $backUrl() : $backUrl;
        $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;

        // Alamat tujuan dibuat server dari route publik; pemeriksaan host ini hanya
        // jaring pengaman agar pesan tidak pernah dikirim ke domain lain.
        if (is_string($host) && strtolower($host) === strtolower((string) config('portal_domains.public'))) {
            return redirect()->away(CartNotice::url($url, $message, $request));
        }

        return redirect()->route('cart.index')->with('error', $message);
    }
}
