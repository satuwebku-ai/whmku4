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
     * Semua perubahan dijaga hasColumn() supaya AMAN dijalankan ulang --
     * migrasi sebelumnya gagal di tengah jalan (bagian nomor 1 sudah
     * terlanjur berhasil), jadi tanpa penjagaan ini akan error "column
     * already exists" saat diulang.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('product_categories', 'type')) {
            Schema::table('product_categories', function (Blueprint $table) {
                $table->enum('type', ['hosting', 'vps'])->default('hosting')->after('slug');
            });
        }

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

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('billing_mode');
        });
    }
};
