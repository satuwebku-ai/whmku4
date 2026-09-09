<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\LoginAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    // ── Manajemen admin ──────────────────────────────────────────────

    public function admins(Request $request): View
    {
        return view('admin.admins.index', $this->adminsData($request));
    }

    public function adminsBootstrap(Request $request): View
    {
        return view('admin.admins.index', $this->adminsData($request));
    }

    private function adminsData(Request $request): array
    {
        $admins = Admin::query()
            ->when($request->search, fn ($q) => $q->where(function ($w) use ($request) {
                $w->where('name', 'like', "%{$request->search}%")
                  ->orWhere('username', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return compact('admins');
    }

    public function create(): View
    {
        return view('admin.admins.form', ['admin' => new Admin()]);
    }

    public function createBootstrap(): View
    {
        return view('admin.admins.form', ['admin' => new Admin()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'username'    => ['required', 'string', 'max:50', 'alpha_dash', 'unique:admins,username'],
            'email'       => ['required', 'email', 'max:255', 'unique:admins,email'],
            'role'        => ['required', 'in:superadmin,admin,staff'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'in:' . implode(',', array_keys(Admin::MODULES))],
            'password'    => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $data['permissions'] = $this->resolvePermissionsInput($request, $data['role']);

        Admin::create($data);

        return redirect()->route('admin.admins')->with('success', 'Admin baru berhasil dibuat.');
    }

    public function edit(Admin $admin): View
    {
        return view('admin.admins.form', compact('admin'));
    }

    public function editBootstrap(Admin $admin): View
    {
        return view('admin.admins.form', compact('admin'));
    }

    public function update(Request $request, Admin $admin): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'username'    => ['required', 'string', 'max:50', 'alpha_dash', 'unique:admins,username,' . $admin->id],
            'email'       => ['required', 'email', 'max:255', 'unique:admins,email,' . $admin->id],
            'role'        => ['required', 'in:superadmin,admin,staff'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'in:' . implode(',', array_keys(Admin::MODULES))],
            'password'    => ['nullable', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        // Password kosong berarti tidak diganti.
        if (blank($data['password'])) {
            unset($data['password']);
        }

        $isSelf = $admin->id === Auth::guard('admin')->id();

        // Jangan sampai seseorang menurunkan perannya sendiri lalu terkunci
        // dari halaman manajemen admin.
        if ($isSelf && $data['role'] !== 'superadmin' && $admin->isSuperadmin()) {
            return back()->with('error', 'Anda tidak bisa menurunkan peran akun sendiri. Minta superadmin lain yang melakukannya.');
        }

        $data['is_active'] = $isSelf ? true : $request->boolean('is_active');
        $data['permissions'] = $this->resolvePermissionsInput($request, $data['role']);

        if ($this->wouldRemoveLastSuperadmin($admin, $data['role'], $data['is_active'])) {
            return back()->with('error', 'Harus ada minimal satu superadmin aktif. Angkat admin lain dulu sebelum mengubah yang ini.');
        }

        $admin->update($data);

        return redirect()->route('admin.admins')->with('success', 'Data admin berhasil diperbarui.');
    }

    /**
     * Ubah input checkbox "permissions[]" dari form jadi nilai yang siap
     * disimpan ke kolom `permissions`.
     *
     * - Superadmin: NULL, tidak relevan (selalu lolos semua modul).
     * - Admin/Staff: kalau form checkbox-nya benar-benar tampil (ditandai
     *   hidden field permissions_submitted), simpan PERSIS apa yang
     *   dicentang -- termasuk array kosong kalau sengaja tidak ada yang
     *   dicentang (dikunci total dari semua modul). Kalau form itu tidak
     *   ada (mis. dipanggil lewat cara lain di luar UI), NULL supaya
     *   jatuh ke bawaan peran, bukan diam-diam mengunci semua modul.
     */
    private function resolvePermissionsInput(Request $request, string $role): ?array
    {
        if ($role === 'superadmin') {
            return null;
        }

        if (! $request->has('permissions_submitted')) {
            return null;
        }

        return array_values(array_intersect(
            $request->input('permissions', []),
            array_keys(Admin::MODULES)
        ));
    }

    /**
     * Blokir / aktifkan kembali akun admin.
     */
    public function toggleStatus(Request $request): RedirectResponse
    {
        $admin = Admin::findOrFail($request->input('admin_id'));

        if ($admin->id === Auth::guard('admin')->id()) {
            return back()->with('error', 'Anda tidak bisa memblokir akun sendiri.');
        }

        $baru = ! $admin->is_active;

        if ($this->wouldRemoveLastSuperadmin($admin, $admin->role, $baru)) {
            return back()->with('error', 'Harus ada minimal satu superadmin aktif.');
        }

        $admin->update(['is_active' => $baru]);

        return back()->with('success', "Akun {$admin->username} berhasil " . ($baru ? 'diaktifkan.' : 'diblokir.'));
    }

    public function destroy(Admin $admin): RedirectResponse
    {
        if ($admin->id === Auth::guard('admin')->id()) {
            return back()->with('error', 'Anda tidak bisa menghapus akun sendiri.');
        }

        if ($this->wouldRemoveLastSuperadmin($admin, null, false)) {
            return back()->with('error', 'Harus ada minimal satu superadmin aktif.');
        }

        $username = $admin->username;
        $admin->delete();

        return redirect()->route('admin.admins')->with('success', "Admin {$username} dihapus.");
    }

    /**
     * Cek apakah perubahan ini menyisakan nol superadmin aktif.
     *
     * Tanpa penjagaan ini, satu klik keliru bisa membuat tidak ada seorang
     * pun yang bisa mengelola admin lagi — dan itu hanya bisa diperbaiki
     * lewat database secara manual.
     */
    private function wouldRemoveLastSuperadmin(Admin $admin, ?string $newRole, bool $newActive): bool
    {
        if (! $admin->isSuperadmin() || ! $admin->is_active) {
            return false;
        }

        $masihSuperadmin = $newRole === 'superadmin' && $newActive;

        if ($masihSuperadmin) {
            return false;
        }

        return Admin::where('role', 'superadmin')
            ->where('is_active', true)
            ->where('id', '!=', $admin->id)
            ->doesntExist();
    }

    // ── Catatan percobaan login ──────────────────────────────────────

    public function loginAttempts(Request $request): View
    {
        return view('admin.admins.login-attempts', $this->loginAttemptsData($request));
    }

    public function loginAttemptsBootstrap(Request $request): View
    {
        return view('admin.admins.login-attempts', $this->loginAttemptsData($request));
    }

    private function loginAttemptsData(Request $request): array
    {
        $attempts = LoginAttempt::query()
            ->when($request->guard, fn ($q) => $q->where('guard', $request->guard))
            ->when($request->result === 'failed', fn ($q) => $q->where('successful', false))
            ->when($request->result === 'success', fn ($q) => $q->where('successful', true))
            ->when($request->search, fn ($q) => $q->where(function ($w) use ($request) {
                $w->where('identifier', 'like', "%{$request->search}%")
                  ->orWhere('ip_address', 'like', "%{$request->search}%");
            }))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $counts = [
            'total'   => LoginAttempt::count(),
            'failed'  => LoginAttempt::failed()->where('created_at', '>=', now()->subDay())->count(),
            'success' => LoginAttempt::where('successful', true)->where('created_at', '>=', now()->subDay())->count(),
        ];

        // IP dengan kegagalan terbanyak 24 jam terakhir — tanda paling jelas
        // ada yang sedang menebak-nebak password.
        $suspicious = LoginAttempt::failed()
            ->where('created_at', '>=', now()->subDay())
            ->selectRaw('ip_address, COUNT(*) as jumlah')
            ->whereNotNull('ip_address')
            ->groupBy('ip_address')
            ->havingRaw('COUNT(*) >= 5')
            ->orderByDesc('jumlah')
            ->limit(5)
            ->get();

        return compact('attempts', 'counts', 'suspicious');
    }

    public function clearAttempts(): RedirectResponse
    {
        $deleted = LoginAttempt::where('created_at', '<', now()->subDays(30))->delete();

        return back()->with('success', "{$deleted} catatan lebih dari 30 hari dihapus.");
    }
}
