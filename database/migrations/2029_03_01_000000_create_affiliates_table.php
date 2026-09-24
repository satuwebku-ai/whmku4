<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu profil affiliate = satu Client (guard yang sama dipakai untuk
     * login, bukan akun terpisah) yang mendaftar jadi affiliate lewat
     * Client\AffiliateController. `code` dipakai di URL referral
     * (`/ref/{code}`).
     */
    public function up(): void
    {
        Schema::create('affiliates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('code')->unique();
            $table->enum('status', ['pending', 'approved', 'rejected', 'suspended'])->default('pending');
            $table->string('affiliate_type')->default('individual');
            $table->string('company_name')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('country')->default('Indonesia');
            $table->string('payment_method')->default('bank_transfer');
            // Override komisi per-affiliate. Null berarti pakai default
            // global (Settings: affiliate_commission_type/value).
            $table->enum('commission_type', ['percentage', 'fixed'])->nullable();
            $table->decimal('commission_value', 12, 2)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejected_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliates');
    }
};
