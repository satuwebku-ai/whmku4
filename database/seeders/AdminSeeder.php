<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Gunakan kredensial dari .env pada server. Default hanya untuk instalasi lokal.
        $email = env('SEED_ADMIN_EMAIL', 'admin@example.com');
        $password = env('SEED_ADMIN_PASSWORD', 'ChangeMe-Immediately-2026!');

        if (app()->environment('production') && ! env('SEED_ADMIN_PASSWORD')) {
            throw new RuntimeException('Atur SEED_ADMIN_PASSWORD yang kuat di .env sebelum menjalankan AdminSeeder di production.');
        }

        $admin = Admin::firstOrNew(['email' => $email]);
        if (! $admin->exists) {
            $admin->fill([
                'name' => env('SEED_ADMIN_NAME', 'Administrator'),
                'username' => env('SEED_ADMIN_USERNAME', 'admin'),
                'password' => Hash::make($password),
                'role' => 'superadmin',
                'permissions' => null,
                'is_active' => true,
                'two_factor_enabled' => false,
            ])->save();
        }
    }
}
