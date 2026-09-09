<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nameserver server hosting ini — dipakai otomatis mengarahkan
     * domain begitu klien beli hosting untuk domain yang sudah terdaftar
     * di sistem kita (lihat ProvisioningService::provisionHosting()).
     */
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('ns1')->nullable()->after('hostname');
            $table->string('ns2')->nullable()->after('ns1');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['ns1', 'ns2']);
        });
    }
};
