<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payment sudah mencatat pembayaran gateway, Credit (sebelumnya
     * ClientBalanceLog) sudah mencatat mutasi saldo -- tabel ini untuk
     * pandangan gabungan lintas keduanya (mis. laporan keuangan yang
     * butuh melihat semua jenis transaksi uang masuk/keluar dalam satu
     * daftar terurut waktu), TIDAK menggantikan keduanya.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['charge', 'refund', 'adjustment']);
            $table->decimal('amount', 12, 2);
            $table->string('description');
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
