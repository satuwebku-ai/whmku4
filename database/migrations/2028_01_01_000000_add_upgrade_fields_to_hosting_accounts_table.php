<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hosting_accounts', function (Blueprint $table) {
            // Sebelumnya HostingAccount hanya menyimpan hasil turunan dari
            // Product (nama paket, harga) tanpa jejak Product aslinya —
            // cukup untuk aktivasi awal, tapi tidak cukup untuk mencari
            // "paket sejenis apa saja yang bisa jadi tujuan upgrade".
            $table->foreignId('product_id')->nullable()->after('client_id')
                ->constrained()->nullOnDelete();

            // Upgrade menunggu pembayaran — mirip pola renewal_invoice_id,
            // dua kolom ini dikosongkan lagi begitu invoice-nya lunas dan
            // upgrade benar-benar diterapkan.
            $table->foreignId('pending_upgrade_product_id')->nullable()->after('renewal_invoice_id')
                ->constrained('products')->nullOnDelete();
            $table->foreignId('pending_upgrade_invoice_id')->nullable()->after('pending_upgrade_product_id')
                ->constrained('invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hosting_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
            $table->dropConstrainedForeignId('pending_upgrade_product_id');
            $table->dropConstrainedForeignId('pending_upgrade_invoice_id');
        });
    }
};
