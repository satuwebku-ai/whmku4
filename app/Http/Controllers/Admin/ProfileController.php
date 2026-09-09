<?php

namespace App\Http\Controllers\Admin;

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
        return view('admin.profile.edit', ['admin' => Auth::guard('admin')->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email,' . $admin->id],
        ]);

        $admin->update($data);

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        if (! Hash::check($data['current_password'], $admin->password)) {
            return back()->withErrors(['current_password' => 'Password saat ini salah.']);
        }

        $admin->update(['password' => $data['password']]);

        return back()->with('success', 'Password berhasil diganti.');
    }

    /**
     * Aktifkan / nonaktifkan verifikasi dua langkah lewat email.
     */
    public function toggleTwoFactor(Request $request): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();

        // Menonaktifkan 2FA butuh konfirmasi password — kalau tidak,
        // sesi yang dibajak bisa mematikan proteksi tanpa hambatan.
        if ($admin->two_factor_enabled) {
            $request->validate(['current_password' => ['required', 'string']]);

            if (! Hash::check($request->input('current_password'), $admin->password)) {
                return back()->withErrors(['current_password' => 'Password salah. 2FA tidak dinonaktifkan.']);
            }
        }

        $admin->update(['two_factor_enabled' => ! $admin->two_factor_enabled]);
        $admin->clearOtp();

        return back()->with('success', $admin->two_factor_enabled
            ? 'Verifikasi dua langkah AKTIF. Login berikutnya akan meminta kode dari email Anda.'
            : 'Verifikasi dua langkah dinonaktifkan.');
    }
}
