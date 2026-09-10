<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // default_ns2 baru dibuat belakangan (2029_01_01) -- kalau belum
        // ada, tunda ke catch-up migration
        // 2029_01_01_000001_catchup_deferred_registrars_default_ns2_columns.php
        if (! Schema::hasColumn('registrars', 'default_ns2') || Schema::hasColumn('registrars', 'documents_email')) {
            return;
        }

        Schema::table('registrars', function (Blueprint $table) {
            // Alamat email bawaan untuk tombol "Kirim Dokumen ke Registrar"
            // di halaman detail domain -- opsional, admin tetap bisa
            // mengetik alamat lain secara manual saat mengirim.
            $table->string('documents_email')->nullable()->after('default_ns2');
        });
    }

    public function down(): void
    {
        Schema::table('registrars', function (Blueprint $table) {
            $table->dropColumn('documents_email');
        });
    }
};
