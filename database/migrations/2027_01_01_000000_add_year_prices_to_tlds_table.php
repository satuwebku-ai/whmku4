<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tlds', function (Blueprint $table) {
            // Harga khusus per durasi, dalam bentuk {"2": 380000, "3": 550000}.
            // Kosong berarti harga dihitung linier: harga_1_tahun × jumlah tahun.
            // Dipakai untuk memberi diskon pada pembelian jangka panjang.
            $table->json('year_prices')->nullable()->after('transfer_price');
            $table->json('year_renew_prices')->nullable()->after('year_prices');
        });
    }

    public function down(): void
    {
        Schema::table('tlds', function (Blueprint $table) {
            $table->dropColumn(['year_prices', 'year_renew_prices']);
        });
    }
};
