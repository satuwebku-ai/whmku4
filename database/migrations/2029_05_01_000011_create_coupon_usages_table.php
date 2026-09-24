<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * coupons.usage_count cuma angka total -- tabel ini catat SIAPA
     * pakai kupon apa, kapan, dan berapa diskonnya, supaya bisa
     * ditelusuri per klien (dipakai juga untuk validasi
     * usage_limit_per_client yang sudah ada di Coupon::validateFor()).
     */
    public function up(): void
    {
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('discount_amount', 12, 2);
            $table->enum('status', ['reserved', 'consumed', 'released'])->default('consumed');
            $table->index(['coupon_id', 'client_id', 'status'], 'coupon_usages_coupon_client_status_index');
            $table->unique('invoice_id', 'coupon_usages_invoice_unique');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
    }
};
