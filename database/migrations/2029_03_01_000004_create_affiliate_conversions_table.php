<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dibuat SEKALI per invoice saat statusnya berubah jadi "paid" DAN
     * client pemilik invoice itu punya affiliate_referrals aktif --
     * lihat App\Models\Invoice::booted() untuk titik pemicunya.
     * unique(invoice_id): satu invoice cuma bisa menghasilkan satu
     * konversi, mencegah duplikat kalau ada race condition di webhook.
     */
    public function up(): void
    {
        Schema::create('affiliate_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('affiliate_referral_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete()->unique();
            // Diambil dari order_type invoice terkait (hosting/domain/vps/
            // other) -- bisa "mixed" kalau satu invoice berisi lebih dari
            // satu jenis produk. Dipakai untuk laporan per jenis produk
            // (bab 18 blueprint: DOMAIN, HOSTING, VPS, SSL, ADDON, RENEWAL).
            $table->string('product_type')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('event_type')->default('first_payment');
            $table->decimal('net_paid_amount', 12, 2)->nullable();
            $table->timestamp('commission_window_expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_conversions');
    }
};
