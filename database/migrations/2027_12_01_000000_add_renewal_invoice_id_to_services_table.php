<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom ini melacak "invoice perpanjangan mana yang sedang menunggu
     * dibayar untuk layanan ini" — tanpa ini, sistem tidak tahu apakah
     * sudah pernah membuat invoice perpanjangan untuk siklus saat ini,
     * dan bisa membuat invoice duplikat tiap kali perintah terjadwal
     * berjalan. Dikosongkan lagi setelah invoice lunas & masa aktif
     * diperpanjang, siap untuk siklus berikutnya.
     */
    public function up(): void
    {
        Schema::table('hosting_accounts', function (Blueprint $table) {
            $table->foreignId('renewal_invoice_id')->nullable()->after('next_due_date')
                ->constrained('invoices')->nullOnDelete();
        });

        Schema::table('domains', function (Blueprint $table) {
            $table->foreignId('renewal_invoice_id')->nullable()->after('expiry_date')
                ->constrained('invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hosting_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('renewal_invoice_id');
        });

        Schema::table('domains', function (Blueprint $table) {
            $table->dropConstrainedForeignId('renewal_invoice_id');
        });
    }
};
