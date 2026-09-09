<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membatalkan fitur "Masa Percobaan Hosting" -- ternyata salah paham
     * dari permintaan aslinya (yang dimaksud sebenarnya siklus tagihan
     * custom dalam hari, bukan trial). Kolom ini dibuang lagi supaya
     * tidak menggantung sebagai sisa yang tidak terpakai.
     */
    public function up(): void
    {
        Schema::table('hosting_accounts', function (Blueprint $table) {
            $table->dropColumn('trial_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('hosting_accounts', function (Blueprint $table) {
            $table->timestamp('trial_expires_at')->nullable()->after('provision_message');
        });
    }
};
