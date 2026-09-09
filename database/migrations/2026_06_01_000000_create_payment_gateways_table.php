<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('name');                      // label yang dilihat klien, mis. "Kartu Kredit / VA"
            $table->string('driver');                    // midtrans, xendit, manual
            $table->string('mode')->default('sandbox');  // sandbox | production
            $table->text('server_key')->nullable();      // dienkripsi — Midtrans Server Key / Xendit Secret Key
            $table->text('client_key')->nullable();      // dienkripsi — Midtrans Client Key
            $table->text('callback_token')->nullable();  // dienkripsi — Xendit callback verification token
            $table->text('instructions')->nullable();    // instruksi transfer manual (nomor rekening dsb)
            $table->decimal('fee_flat', 12, 2)->default(0);      // biaya tambahan tetap
            $table->decimal('fee_percent', 5, 2)->default(0);    // biaya tambahan persentase
            $table->string('currency', 3)->default('IDR');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
