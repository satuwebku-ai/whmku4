<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Desain orders di project ini SATU baris = SATU produk (kolom
     * product_id tunggal + order_type) -- bukan orders+order_items
     * banyak baris seperti blueprint. Tabel ini ditambahkan sebagai
     * rincian baris TAMBAHAN untuk order yang butuh breakdown biaya
     * majemuk (mis. produk + add-on + biaya setup terpisah), TANPA
     * mengubah desain orders yang sudah dipakai luas di checkout.
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
