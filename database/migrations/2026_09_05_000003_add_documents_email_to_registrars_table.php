<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
