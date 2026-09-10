<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // hosting = produk hosting biasa, vps = produk VPS/cloud --
            // dipakai form Tambah Produk untuk menyesuaikan isian & menyaring
            // pilihan server.
            $table->enum('type', ['hosting', 'vps'])->default('hosting');
            $table->text('description')->nullable();
            $table->string('icon')->nullable(); // nama ikon Font Awesome, mis. "fa-server"
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
