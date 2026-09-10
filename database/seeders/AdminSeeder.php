<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Membuat akun admin default.
     * Login: username = "admin", password = "password"
     * WAJIB diganti setelah login pertama.
     */
    public function run(): void
    {
        Admin::updateOrCreate(
            ['username' => 'admin'],
            [
                'name'      => 'Super Admin',
                'email'     => 'satuinfoku@gmail.com',
                'password'  => Hash::make('S@yaNo01'),
                'role'      => 'superadmin',
                'is_active' => true,
            ]
        );
    }
}
