<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Siklus tagihan custom (dalam hari) — cuma untuk produk Hosting/VPS.
     * custom_cycle_days menentukan berapa hari 1 siklus "Custom" itu
     * (mis. 45 hari) — cuma bisa diisi Superadmin (dijaga di controller,
     * bukan di database).
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price_custom', 12, 2)->nullable()->after('price_annually');
            $table->unsignedSmallInteger('custom_cycle_days')->nullable()->after('price_custom');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['price_custom', 'custom_cycle_days']);
        });
    }
};
