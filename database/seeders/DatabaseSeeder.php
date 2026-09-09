<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Hanya akun admin yang dibuat otomatis — data contoh TIDAK ikut,
        // supaya instalasi produksi langsung bersih.
        //
        // Kalau butuh data contoh untuk latihan/uji coba, jalankan manual:
        //   php artisan db:seed --class=DemoDataSeeder
        //
        // Dan untuk menghapusnya kembali:
        //   php artisan lumora:clear-demo --all
        $this->call([
            AdminSeeder::class,
        ]);
    }
}
