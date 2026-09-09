<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sebelumnya "extension" unik SENDIRIAN -- artinya satu ekstensi
     * (mis. ".com") cuma bisa dimiliki SATU registrar dalam satu waktu.
     * Kalau dua registrar sama-sama menjual ".com" (mis. Liqu.id DAN
     * DNAMA), yang kedua akan "menimpa" data yang pertama saat
     * sinkronisasi, bukan tersimpan sebagai baris terpisah.
     *
     * Sekarang unik per KOMBINASI (extension, registrar_id) -- ".com"
     * milik Liqu.id dan ".com" milik DNAMA adalah dua baris yang
     * sah-sah saja hidup berdampingan, admin yang pilih mana yang mau
     * diaktifkan/dijual.
     */
    public function up(): void
    {
        Schema::table('tlds', function (Blueprint $table) {
            $table->dropUnique('tlds_extension_unique');
            $table->unique(['extension', 'registrar_id'], 'tlds_extension_registrar_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tlds', function (Blueprint $table) {
            $table->dropUnique('tlds_extension_registrar_unique');
            $table->unique('extension', 'tlds_extension_unique');
        });
    }
};
