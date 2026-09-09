<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catatan: order <-> domain TIDAK butuh kolom baru di sini — tabel
     * "domains" sudah punya order_id sejak Fase 4 (Domain::order() belongsTo).
     * Order::domain() cukup didefinisikan sebagai hasOne yang memakai kolom
     * itu, tanpa foreign key balik di tabel orders.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Produk katalog yang dipesan — dipakai saat provisioning untuk
            // tahu server tujuan & nama package cPanel (products.server_id
            // dan products.panel_package dari Fase 7b).
            $table->foreignId('product_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
        });
    }
};
