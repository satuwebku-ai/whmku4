<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dibuat SEKALI saat visitor yang terlacak (cookie affiliate_ref
     * masih ada) benar-benar mendaftar jadi Client -- bukan saat klik.
     * `client_id` unique: satu client cuma bisa "dimiliki" satu affiliate
     * (atribusi klik pertama yang menang, most-common model referral).
     */
    public function up(): void
    {
        Schema::create('affiliate_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('affiliate_click_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete()->unique();
            $table->enum('status', ['pending', 'converted'])->default('pending');
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_referrals');
    }
};
