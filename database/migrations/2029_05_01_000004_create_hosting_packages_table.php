<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * products.panel_package cuma string bebas (nama plan di WHM/cPanel).
     * Tabel ini opsional untuk produk hosting yang butuh kuota
     * TERSTRUKTUR (bisa difilter/dibandingkan), bukan pengganti kolom
     * panel_package -- keduanya bisa dipakai bersamaan.
     */
    public function up(): void
    {
        Schema::create('hosting_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('disk_quota_mb')->nullable(); // null = unlimited
            $table->unsignedInteger('bandwidth_mb')->nullable();
            $table->unsignedInteger('email_accounts')->nullable();
            $table->unsignedInteger('databases')->nullable();
            $table->unsignedInteger('ftp_accounts')->nullable();
            $table->unsignedInteger('addon_domains')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hosting_packages');
    }
};
