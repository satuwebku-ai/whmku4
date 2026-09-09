<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tlds', function (Blueprint $table) {
            // TLD demo: dipakai untuk mencoba alur pemesanan dari awal
            // sampai akhir tanpa menyentuh registrar atau domain sungguhan.
            //
            // Pengecekan ketersediaannya dilewati (selalu dianggap tersedia)
            // karena TLD cadangan seperti .test memang tidak punya server
            // RDAP — kalau tidak dilewati, statusnya akan selalu "belum
            // pasti" dan tombol Tambah tidak pernah muncul.
            $table->boolean('is_demo')->default(false)->after('show_in_search');
        });
    }

    public function down(): void
    {
        Schema::table('tlds', function (Blueprint $table) {
            $table->dropColumn('is_demo');
        });
    }
};
