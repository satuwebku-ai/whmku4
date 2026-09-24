<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ledger wallet affiliate -- pola & alasannya identik dengan
     * client_balance_logs (lihat App\Models\Client::adjustBalance()):
     * saldo TIDAK PERNAH diubah langsung, selalu lewat baris ledger di
     * sini supaya ada jejak audit lengkap kenapa saldo naik/turun.
     */
    public function up(): void
    {
        Schema::create('affiliate_commission_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('product_type')->nullable();
            $table->string('event_type')->default('every_payment');
            $table->string('commission_type')->default('percentage');
            $table->decimal('commission_value', 12, 4);
            $table->unsignedInteger('duration_days')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['product_type', 'event_type', 'is_active']);
        });

        Schema::create('affiliate_fraud_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('affiliate_referral_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('affiliate_conversion_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('review');
            $table->string('reason');
            $table->json('signals')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reviewer_notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('affiliate_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('affiliate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index(['affiliate_id', 'action']);
        });

        Schema::create('affiliate_commission_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_commission_id')->constrained()->cascadeOnDelete()->unique();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('reason');
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('affiliate_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['commission', 'payout', 'adjustment']);
            // Positif untuk komisi masuk, negatif untuk payout/koreksi keluar.
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('description');
            $table->foreignId('affiliate_commission_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('affiliate_payout_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('affiliate_commission_reversal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_wallet_transactions');
        Schema::dropIfExists('affiliate_commission_reversals');
        Schema::dropIfExists('affiliate_audit_logs');
        Schema::dropIfExists('affiliate_fraud_flags');
        Schema::dropIfExists('affiliate_commission_rules');
    }
};
