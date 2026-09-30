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
            // Harga modal disimpan terpisah dari harga jual. Harga jual
            // tetap menjadi sumber harga checkout dan renewal.
            $table->decimal('cost_price_monthly', 12, 2)->nullable();
            $table->decimal('cost_price_quarterly', 12, 2)->nullable();
            $table->decimal('cost_price_semi_annually', 12, 2)->nullable();
            $table->decimal('cost_price_annually', 12, 2)->nullable();
            $table->string('pricing_source')->default('manual');
            $table->boolean('is_public')->default(true);
            $table->string('supplier_api_url')->nullable();
            $table->string('supplier_http_method')->default('GET');
            $table->text('supplier_api_token')->nullable();
            $table->string('supplier_price_path_monthly')->nullable();
            $table->string('supplier_price_path_quarterly')->nullable();
            $table->string('supplier_price_path_semi_annually')->nullable();
            $table->string('supplier_price_path_annually')->nullable();
            $table->timestamp('supplier_last_synced_at')->nullable();
            $table->text('supplier_last_error')->nullable();
            // ssl | license -- dipakai untuk filter di halaman katalog Lisensi.
            $table->string('category', 30)->default('license')->index();
            $table->string('brand', 100)->nullable();
            $table->string('summary', 255)->nullable();
            $table->text('long_description')->nullable();
            // Daftar fitur (array string), spesifikasi (label => nilai), dan FAQ (array {q, a}).
            $table->json('features')->nullable();
            $table->json('specs')->nullable();
            $table->json('faqs')->nullable();

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
            $table->unique(
                ['hosting_account_id', 'addon_id'],
                'hosting_account_addons_hosting_addon_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hosting_account_addons');
        Schema::dropIfExists('addons');
    }
};
