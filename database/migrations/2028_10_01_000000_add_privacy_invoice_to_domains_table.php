<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sebelum ini, klien bisa mengaktifkan ID Protection sendiri secara
     * GRATIS lewat tombol di halaman domain — padahal tiap aktivasi
     * memotong saldo deposit reseller di Liqu.id (sekitar Rp 76.000).
     * Dengan kolom ini, aktivasi ditahan sampai invoice-nya dibayar,
     * mengikuti pola yang sama seperti Addon & Upgrade Paket.
     */
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->foreignId('privacy_invoice_id')->nullable()->after('whois_privacy')
                ->constrained('invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropConstrainedForeignId('privacy_invoice_id');
        });
    }
};
