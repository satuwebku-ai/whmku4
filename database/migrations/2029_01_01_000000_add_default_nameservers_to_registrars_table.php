<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nameserver default per registrar — dipakai otomatis saat domain
     * baru didaftarkan (kalau klien tidak menentukan nameserver sendiri),
     * supaya domain tidak dibiarkan tanpa nameserver sama sekali. Nanti
     * begitu klien beli hosting untuk domain yang sama, ini akan ditimpa
     * otomatis dengan nameserver server hosting-nya (lihat
     * ProvisioningService).
     */
    public function up(): void
    {
        Schema::table('registrars', function (Blueprint $table) {
            $table->string('default_ns1')->nullable()->after('client_ip');
            $table->string('default_ns2')->nullable()->after('default_ns1');
        });
    }

    public function down(): void
    {
        Schema::table('registrars', function (Blueprint $table) {
            $table->dropColumn(['default_ns1', 'default_ns2']);
        });
    }
};
