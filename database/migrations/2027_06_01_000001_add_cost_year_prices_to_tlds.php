<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Harga MODAL per tahun (2-10 tahun), diisi otomatis saat sinkronisasi
     * dari registrar yang menyediakannya (DNAMA iya, lewat field
     * "pricings" per durasi -- lihat DnamaService::listPrices()).
     *
     * Terpisah dari year_prices/year_renew_prices yang sudah ada di
     * migration 2027_01_01 -- kolom ITU untuk harga JUAL (yang admin
     * tetapkan manual), kolom INI untuk harga MODAL (dari registrar,
     * referensi supaya admin tahu untung-ruginya per durasi sebelum
     * menetapkan harga jual per tahun).
     */
    public function up(): void
    {
        Schema::table('tlds', function (Blueprint $table) {
            $table->json('cost_year_prices')->nullable()->after('cost_currency');
            $table->json('cost_year_renew_prices')->nullable()->after('cost_year_prices');
        });
    }

    public function down(): void
    {
        Schema::table('tlds', function (Blueprint $table) {
            $table->dropColumn(['cost_year_prices', 'cost_year_renew_prices']);
        });
    }
};
