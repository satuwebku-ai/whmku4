<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom price_monthly/price_quarterly/dst di tabel products TETAP
     * dipertahankan (dipakai luas di checkout/cart) -- tabel ini
     * TAMBAHAN, representasi baris-per-siklus untuk kebutuhan laporan
     * atau harga khusus per grup klien di masa depan (client_group_id
     * nullable = null berarti harga umum).
     */
    public function up(): void
    {
        Schema::create('product_pricings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('billing_cycle', ['monthly', 'quarterly', 'semi_annually', 'annually', 'custom']);
            $table->decimal('price', 12, 2);
            $table->decimal('setup_fee', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'client_group_id', 'billing_cycle'], 'product_pricing_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_pricings');
    }
};
