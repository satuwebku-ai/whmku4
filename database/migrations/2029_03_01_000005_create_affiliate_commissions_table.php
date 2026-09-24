<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('affiliate_conversion_id')->nullable()->constrained()->cascadeOnDelete()->unique();
            $table->decimal('amount', 12, 2);
            // pending: baru dihitung, belum masuk wallet -- jeda ini
            // sengaja ada supaya admin bisa membatalkan komisi dari
            // transaksi yang di-refund sebelum uangnya cair ke affiliate.
            $table->string('status')->default('pending');
            $table->string('commission_type')->nullable();
            $table->decimal('commission_rate', 12, 4)->nullable();
            $table->decimal('commission_base', 12, 2)->nullable();
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->nullable();
            $table->string('event_type')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('cancelled_reason')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_commissions');
    }
};
