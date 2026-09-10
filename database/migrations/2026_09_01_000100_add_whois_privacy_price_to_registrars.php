<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Harga default ID Protection per registrar -- NULL berarti ikut
     * harga global (Setting whois_privacy_price). Ini tingkatan
     * PERTENGAHAN antara harga global dan harga per-TLD:
     *
     *   harga per-TLD (kalau diisi)
     *     -> harga per-registrar (kalau diisi)
     *       -> harga global (fallback terakhir)
     */
    public function up(): void
    {
        // default_ns2 baru dibuat belakangan (2029_01_01) -- kalau belum
        // ada, tunda ke catch-up migration
        // 2029_01_01_000001_catchup_deferred_registrars_default_ns2_columns.php
        if (! Schema::hasColumn('registrars', 'default_ns2') || Schema::hasColumn('registrars', 'whois_privacy_price')) {
            return;
        }

        Schema::table('registrars', function (Blueprint $table) {
            $table->decimal('whois_privacy_price', 12, 2)->nullable()->after('default_ns2');
        });
    }

    public function down(): void
    {
        Schema::table('registrars', function (Blueprint $table) {
            $table->dropColumn('whois_privacy_price');
        });
    }
};
