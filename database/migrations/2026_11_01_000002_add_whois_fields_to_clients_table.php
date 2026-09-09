<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registrasi domain otomatis (Fase 7c) butuh data kontak WHOIS lengkap
     * (lihat Fase 4 — Namecheap/Liqu.id mewajibkan provinsi & kode pos).
     * Sebelumnya form klien hanya punya kota & negara.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('state')->nullable()->after('city');
            $table->string('postal_code')->nullable()->after('state');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['state', 'postal_code']);
        });
    }
};
