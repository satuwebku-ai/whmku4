<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dipindah dari 2026_08_28_065626_make_promo_banners_title_nullable.php
     * -- tabel promo_banners baru dibuat di file ini (2028_12_02).
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
