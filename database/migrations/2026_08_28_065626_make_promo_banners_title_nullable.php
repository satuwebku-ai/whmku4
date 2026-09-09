<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menyusul perubahan form Banner Promo yang membuat Judul jadi
     * opsional (boleh kosong kalau gambar bannernya sudah punya teks
     * sendiri) -- kolom di database perlu ikut diizinkan null, karena
     * sebelumnya wajib diisi.
     */
    public function up(): void
    {
        Schema::table('promo_banners', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('promo_banners', function (Blueprint $table) {
            $table->string('title')->nullable(false)->change();
        });
    }
};