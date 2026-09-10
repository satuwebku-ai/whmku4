<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu grup opsi konfigurasi milik SATU produk, mis. "RAM Tambahan" atau
 * "Backup Otomatis". `selection_type` menentukan cara klien memilih:
 *   - checkbox: boleh centang lebih dari satu opsi dalam grup ini
 *   - radio:    cuma boleh pilih SATU opsi (mis. tingkatan RAM: Standar /
 *               +1GB / +2GB) -- kalau is_required aktif, klien wajib
 *               memilih salah satu (admin perlu sediakan opsi harga 0
 *               kalau mau ada pilihan "tidak tambah apa-apa").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_option_groups', function (Blueprint $table) {
            $table->id();
            // constrained() ke 'products' tidak bisa dipasang di sini --
            // tabel products baru dibuat belakangan (2026_10_01). FK-nya
            // dipasang di 2026_10_01_000001_create_products_table.php.
            $table->foreignId('product_id');
            $table->string('name');
            $table->enum('selection_type', ['checkbox', 'radio'])->default('checkbox');
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_option_groups');
    }
};
