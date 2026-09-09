<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();       // PAY-2026-0001, dikirim ke gateway sebagai order_id
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_gateway_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('amount', 12, 2)->default(0);       // nominal invoice
            $table->decimal('fee', 12, 2)->default(0);          // biaya gateway
            $table->decimal('total', 12, 2)->default(0);        // amount + fee, yang benar-benar ditagih
            $table->string('currency', 3)->default('IDR');

            // initiated: link/VA sudah dibuat, menunggu bayar
            // pending  : klien klaim sudah bayar (manual), menunggu verifikasi admin
            // paid     : lunas & terverifikasi
            // failed / expired / refunded
            $table->enum('status', ['initiated', 'pending', 'paid', 'failed', 'expired', 'refunded'])
                  ->default('initiated');

            $table->string('external_id')->nullable();    // transaction_id dari gateway
            $table->string('payment_method')->nullable(); // bank_transfer, gopay, credit_card, dll
            $table->text('payment_url')->nullable();      // Snap URL / Xendit invoice URL
            $table->string('proof_path')->nullable();     // bukti transfer manual (upload)
            $table->json('gateway_response')->nullable(); // respons mentah untuk audit
            $table->text('admin_note')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
