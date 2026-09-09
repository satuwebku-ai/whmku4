<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\LoginAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Memungkinkan admin masuk sebagai klien tertentu — untuk membantu
 * troubleshooting ("klien bilang tombolnya tidak bisa diklik, coba saya
 * lihat dari sisi mereka") tanpa perlu tahu atau mengubah password klien.
 *
 * Dua guard (admin & client) berjalan independen dalam satu browser, jadi
 * sesi login admin TIDAK ikut hilang selama impersonasi — hanya guard
 * client yang berpindah ke akun target.
 *
 * Setiap mulai/selesai impersonasi dicatat ke ActivityLog dan LoginAttempt,
 * supaya ada jejak jelas siapa yang pernah mengakses akun siapa dan kapan.
 * Ini bukan detail teknis kosmetik — kalau data klien berubah saat
 * diimpersonasi, jejak ini yang menjelaskan kenapa.
 */
class ImpersonateController extends Controller
{
    /**
     * Mulai impersonasi. Hanya bisa dipicu dari sesi admin yang sudah
     * terautentikasi (dijamin middleware 'auth:admin' + 'role' di route).
     */
    public function start(Request $request, Client $client): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();

        // Simpan siapa yang memulai, supaya tombol "Kembali ke Admin"
        // tahu ini sungguh sesi impersonasi — bukan login klien biasa
        // yang kebetulan terjadi di browser yang sama.
        $request->session()->put('impersonator_admin_id', $admin->id);
        $request->session()->put('impersonator_admin_name', $admin->name);

        Auth::guard('client')->login($client);
        $request->session()->regenerate();

        ActivityLog::record(
            'client',
            "Admin masuk sebagai klien: {$client->name}",
            "{$admin->name} mengakses akun {$client->email} untuk keperluan dukungan.",
            route('admin.clients.details', $client),
            'warning',
            $client->id,
        );

        LoginAttempt::record('client', $client->email, true, 'impersonated', $request);

        return redirect()->route('client.dashboard')
            ->with('success', "Anda sekarang masuk sebagai {$client->name}.");
    }

    /**
     * Sudahi impersonasi, kembali ke sesi admin (yang tidak pernah hilang).
     */
    public function stop(Request $request): RedirectResponse
    {
        $adminId = $request->session()->get('impersonator_admin_id');

        // Bukan hasil dari fitur ini (mis. klien memakai tombol ini lewat
        // URL langsung tanpa pernah diimpersonasi) — jangan proses.
        if (! $adminId) {
            return redirect()->route('client.dashboard');
        }

        $client = Auth::guard('client')->user();

        if ($client) {
            ActivityLog::record(
                'client',
                "Admin mengakhiri sesi sebagai klien: {$client->name}",
                $request->session()->get('impersonator_admin_name') . " kembali ke panel admin.",
                route('admin.clients.details', $client),
                'info',
                $client->id,
            );
        }

        Auth::guard('client')->logout();
        $request->session()->forget(['impersonator_admin_id', 'impersonator_admin_name']);
        $request->session()->regenerate();

        return redirect()->route('admin.clients')
            ->with('success', 'Kembali ke panel admin.');
    }
}
