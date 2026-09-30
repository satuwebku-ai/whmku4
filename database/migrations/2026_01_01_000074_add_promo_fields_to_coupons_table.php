<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Halaman Promo publik memakai tabel kupon yang sudah ada (bukan tabel baru):
 *  - tld_ids     : TLD sasaran kupon "Tertentu" (JSON daftar id tlds).
 *  - title/description : judul & keterangan promo untuk halaman publik.
 *  - is_public   : tampilkan kupon ini di halaman Promo (default tidak).
 * Kupon lama tidak berubah perilakunya (semua kolom baru kosong / false).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->json('tld_ids')->nullable()->after('applies_to');
            $table->string('title')->nullable()->after('code');
            $table->text('description')->nullable()->after('title');
            $table->boolean('is_public')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['tld_ids', 'title', 'description', 'is_public']);
        });
    }
};
