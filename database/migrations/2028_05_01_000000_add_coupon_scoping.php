<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            // 'all' (perilaku lama, tetap default supaya kupon yang sudah
            // ada tidak berubah) atau 'specific' (dibatasi ke produk/
            // kategori tertentu lewat dua tabel pivot di bawah).
            $table->enum('applies_to', ['all', 'specific'])->default('all')->after('code');
        });

        // Produk tertentu yang jadi sasaran kupon — dipisah dari kategori
        // supaya admin bisa pilih salah satu, atau gabungan keduanya
        // (mis. "semua produk kategori Hosting" + "satu produk VPS
        // tertentu di luar kategori itu").
        Schema::create('coupon_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['coupon_id', 'product_id']);
        });

        Schema::create('coupon_product_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['coupon_id', 'product_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_product_category');
        Schema::dropIfExists('coupon_product');

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn('applies_to');
        });
    }
};
