<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ID Protection punya masa berlaku SENDIRI (1 tahun sejak aktif),
     * TERPISAH dari masa aktif domain — supaya klien selalu dapat 12
     * bulan penuh berapa pun sisa umur domainnya, dan kita tidak rugi
     * (registrar menagih penuh 1 tahun berapa pun sisa masa domain).
     *
     * Habis setahun, klien memesan lagi kalau mau lanjut.
     */
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->date('privacy_expires_at')->nullable()->after('privacy_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn('privacy_expires_at');
        });
    }
};
