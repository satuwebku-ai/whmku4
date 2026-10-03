<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    /**
     * Membuat akun superadmin awal (username "admin") HANYA jika belum ada —
     * menjalankan seeder lagi tidak menimpa email/password yang sudah diganti.
     *
     * Kredensial diambil dari .env (ADMIN_EMAIL, ADMIN_PASSWORD). Tanpa
     * ADMIN_PASSWORD, dibuat password acak yang ditampilkan sekali di layar.
     * Tidak ada kredensial bawaan di kode sumber.
     */
    public function run(): void
    {
        if (Admin::where('username', 'admin')->exists()) {
            $this->command?->info('Akun admin sudah ada — tidak diubah.');

            return;
        }

        $password = config('lumora.admin.password');
        $generated = blank($password);

        if ($generated) {
            $password = Str::password(16, symbols: false);
        }

        Admin::create([
            'username' => 'admin',
            'name' => 'Super Admin',
            'email' => config('lumora.admin.email') ?: 'admin@example.com',
            'password' => Hash::make($password),
            'role' => 'superadmin',
            'is_active' => true,
        ]);

        $this->command?->info('Akun admin dibuat (username: admin).');

        if ($generated) {
            $this->command?->warn("Password sementara: {$password}  — catat sekarang, lalu ganti setelah login. Tidak akan ditampilkan lagi.");
        }
    }
}
