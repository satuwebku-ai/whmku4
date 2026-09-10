<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 1. product_categories.type -- menentukan jenis produk di dalamnya
     *    (hosting biasa vs VPS/cloud). Dipakai form Tambah Produk untuk
     *    menyesuaikan isian & menyaring pilihan server.
     *
     * 2. products.billing_mode -- khusus produk VPS: ditagih dari saldo
     *    per jam, atau invoice berkala seperti hosting biasa.
     *
     * 3. products.panel_package dilebarkan -- untuk produk VPS kolom ini
     *    berisi JSON spesifikasi (bukan sekadar nama plan WHM), jadi
     *    255 karakter terlalu sempit kalau nanti spek bertambah.
     *
     * CATATAN: product_categories & products BELUM ADA di titik migrasi
     * ini -- keduanya baru dibuat di 2026_10_01 (jauh setelah migrasi
     * ini). Semua perubahan di atas karena itu dipindah ke catch-up
     * migration 2026_10_01_000002_catchup_deferred_product_alterations.php
     * yang jalan setelah tabelnya ada. hasTable() dijaga di sini murni
     * jaga-jaga kalau urutan tabel berubah lagi di masa depan.
     */
    public function up(): void
    {
        if (Schema::hasTable('product_categories') && ! Schema::hasColumn('product_categories', 'type')) {
            Schema::table('product_categories', function (Blueprint $table) {
                $table->enum('type', ['hosting', 'vps'])->default('hosting')->after('slug');
            });
        }

        if (Schema::hasTable('products')) {
            if (! Schema::hasColumn('products', 'billing_mode')) {
                Schema::table('products', function (Blueprint $table) {
                    // Ditempel setelah panel_package -- kolom billing_cycle
                    // TIDAK ADA di tabel products (harga per siklus disimpan
                    // sebagai kolom terpisah: price_monthly, price_quarterly, dst).
                    $table->enum('billing_mode', ['invoice', 'deposit'])->default('invoice')->after('panel_package');
                });
            }

            Schema::table('products', function (Blueprint $table) {
                $table->string('panel_package', 500)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('product_categories', 'type')) {
            Schema::table('product_categories', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }

        if (Schema::hasColumn('products', 'billing_mode')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('billing_mode');
            });
        }
    }
};
