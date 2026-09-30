<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_banners', function (Blueprint $table) {
            $table->id();
            // Boleh kosong kalau gambar bannernya sudah punya teks sendiri.
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->string('image'); // disimpan di public/uploads/banners, BUKAN storage/app/public
            $table->string('link_url')->nullable();
            $table->string('button_text')->nullable();
            $table->boolean('open_in_new_tab')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            // Halaman tujuan banner ditampilkan: Beranda, Katalog, Cek
            // Domain, atau Semua Halaman.
            $table->string('display_page')->default('all');
            // Opsional — kosongkan supaya tayang terus tanpa batas waktu.
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_banners');
    }
};
