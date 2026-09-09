<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu pilihan di dalam grup opsi konfigurasi (lihat
 * product_option_groups), mis. "+1GB RAM (+Rp10.000/bulan)". Kolom harga
 * per siklus meniru pola yang sama persis dengan `products` & `addons` --
 * NULL berarti opsi ini tidak tersedia untuk siklus tagihan itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_option_group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price_monthly', 12, 2)->nullable();
            $table->decimal('price_quarterly', 12, 2)->nullable();
            $table->decimal('price_semi_annually', 12, 2)->nullable();
            $table->decimal('price_annually', 12, 2)->nullable();
            $table->decimal('price_custom', 12, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_options');
    }
};
