<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table) {
            // Kode metode QRIS spesifik Duitku (mis. "SP", "NQ" — beda
            // tergantung channel QRIS yang aktif di akun merchant). Diisi
            // admin sendiri lewat form, BUKAN ditebak sistem — kode yang
            // salah membuat QRIS gagal muncul tanpa pesan error yang jelas.
            // Dikosongkan = fitur QRIS tertanam tidak aktif, klien tetap
            // bisa bayar lewat halaman Duitku biasa (redirect).
            $table->string('qris_method_code')->nullable()->after('callback_token');
        });
    }

    public function down(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->dropColumn('qris_method_code');
        });
    }
};
