<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Checkout lisensi/SSL membuat Order dengan order_type = 'addon', tetapi enum
 * di tabel orders hanya mengenal hosting/domain/vps/other sehingga insert
 * gagal ("Data truncated for column 'order_type'"). Migrasi ini menambahkan
 * nilai 'addon' ke enum tersebut.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE orders MODIFY order_type ENUM('hosting','domain','vps','addon','other') NOT NULL DEFAULT 'hosting'");
        }
        // SQLite (dipakai test) tidak memberlakukan enum sebagai constraint ketat
        // pada kolom yang dibuat lewat Blueprint::enum(), jadi tidak perlu diubah.
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::table('orders')->where('order_type', 'addon')->update(['order_type' => 'other']);
            DB::statement("ALTER TABLE orders MODIFY order_type ENUM('hosting','domain','vps','other') NOT NULL DEFAULT 'hosting'");
        }
    }
};
