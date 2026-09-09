<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opsi konfigurasi yang dipilih klien saat checkout, terpasang permanen
 * ke satu hosting account -- namanya & harganya di-snapshot di sini (bukan
 * cuma menyimpan product_option_id) supaya kalau admin nanti mengubah
 * nama/harga opsi di katalog, riwayat pesanan yang sudah jadi tidak ikut
 * berubah diam-diam. `price` dipakai lagi di setiap invoice perpanjangan
 * (lihat HostingAccount::renewalAmount() & createRenewalInvoice()), sama
 * seperti pola yang sudah ada di hosting_account_addons.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hosting_account_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hosting_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_option_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_option_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('group_name')->nullable();
            $table->string('name');
            $table->decimal('price', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hosting_account_options');
    }
};
