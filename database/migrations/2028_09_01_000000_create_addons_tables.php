<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Katalog add-on yang dikelola admin — mirip Product, tapi lebih
        // sederhana (tidak perlu kategori/domain_option/dst, cuma nama,
        // harga per siklus, dan deskripsi singkat).
        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price_monthly', 12, 2)->nullable();
            $table->decimal('price_quarterly', 12, 2)->nullable();
            $table->decimal('price_semi_annually', 12, 2)->nullable();
            $table->decimal('price_annually', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Addon yang sudah terpasang di suatu layanan hosting — harga
        // di-snapshot saat dipasang (bukan selalu baca ulang dari
        // katalog), supaya kalau admin ubah harga addon di katalog nanti,
        // klien yang sudah pasang duluan tidak ikut berubah tiba-tiba
        // di tengah jalan (sama seperti pola harga produk & TLD).
        Schema::create('hosting_account_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hosting_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('addon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name'); // disalin dari addon saat dipasang, tetap ada meski addon aslinya nanti dihapus
            $table->decimal('price', 12, 2);
            // pending_payment -> active (begitu invoice lunas) -> cancelled (klien berhenti pakai)
            $table->string('status')->default('pending_payment');
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hosting_account_addons');
        Schema::dropIfExists('addons');
    }
};
