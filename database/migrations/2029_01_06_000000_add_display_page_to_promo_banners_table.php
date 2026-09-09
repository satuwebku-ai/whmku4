<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Halaman tujuan banner ini ditampilkan -- sebelumnya SELALU di
     * Katalog saja, sekarang admin bisa pilih (Beranda, Katalog, Cek
     * Domain, atau Semua Halaman).
     */
    public function up(): void
    {
        Schema::table('promo_banners', function (Blueprint $table) {
            $table->string('display_page')->default('all')->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('promo_banners', function (Blueprint $table) {
            $table->dropColumn('display_page');
        });
    }
};
