<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('client.profile.edit', ['client' => Auth::guard('client')->user()]);
    }

    public function editBootstrap(): View
    {
        return view('client.profile.edit', ['client' => Auth::guard('client')->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $client = Auth::guard('client')->user();

        $data = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'email'   => ['required', 'email', 'max:255', 'unique:clients,email,' . $client->id],
            'phone'   => ['required', 'string', 'max:30'],
            'company' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city'    => ['nullable', 'string', 'max:120'],
            'state'   => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:120'],

            'whatsapp_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s]*$/'],
            'notify_promo'    => ['nullable', 'boolean'],
            'notify_whatsapp' => ['nullable', 'boolean'],
            'notify_sms'      => ['nullable', 'boolean'],
        ], [
            'whatsapp_number.regex' => 'Nomor WhatsApp hanya boleh berisi angka.',
        ]);

        // Checkbox tidak terkirim saat tidak dicentang, jadi diisi eksplisit.
        $data['notify_promo'] = $request->boolean('notify_promo');

        // WhatsApp hanya bisa diaktifkan kalau nomornya diisi — mengaktifkan
        // tanpa nomor akan membuat notifikasi diam-diam tidak terkirim.
        $data['notify_whatsapp'] = $request->boolean('notify_whatsapp')
            && filled($data['whatsapp_number'] ?? null);

        $data['notify_sms'] = $request->boolean('notify_sms');

        $client->update($data);

        return back()->with('success', 'Data profil berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $client = Auth::guard('client')->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        if (! Hash::check($data['current_password'], $client->password)) {
            return back()->withErrors(['current_password' => 'Password saat ini salah.']);
        }

        $client->update(['password' => $data['password']]);

        return back()->with('success', 'Password berhasil diganti.');
    }

    public function toggleTwoFactor(Request $request): RedirectResponse
    {
        $client = Auth::guard('client')->user();

        // Menonaktifkan 2FA butuh konfirmasi password — kalau tidak,
        // sesi yang dibajak bisa mematikan proteksi tanpa hambatan.
        if ($client->two_factor_enabled) {
            $request->validate(['current_password' => ['required', 'string']]);

            if (! Hash::check($request->input('current_password'), $client->password)) {
                return back()->withErrors(['current_password' => 'Password salah. 2FA tidak dinonaktifkan.']);
            }
        }

        $client->update(['two_factor_enabled' => ! $client->two_factor_enabled]);
        $client->clearOtp();

        return back()->with('success', $client->two_factor_enabled
            ? 'Verifikasi dua langkah AKTIF. Login berikutnya akan meminta kode dari email Anda.'
            : 'Verifikasi dua langkah dinonaktifkan.');
    }
}
