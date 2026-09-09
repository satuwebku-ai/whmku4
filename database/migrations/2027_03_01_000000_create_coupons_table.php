<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->enum('type', ['percent', 'fixed'])->default('percent');
            $table->decimal('value', 12, 2); // persen (0-100) atau rupiah tetap

            $table->decimal('min_order', 12, 2)->default(0); // subtotal minimum supaya kupon berlaku
            $table->decimal('max_discount', 12, 2)->nullable(); // batas potongan untuk kupon persen

            $table->unsignedInteger('usage_limit')->nullable(); // null = tak terbatas
            $table->unsignedInteger('usage_count')->default(0);
            $table->unsignedInteger('usage_limit_per_client')->default(1);

            $table->date('starts_at')->nullable();
            $table->date('expires_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
            $table->decimal('discount', 12, 2)->default(0)->after('tax');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn('discount');
        });

        Schema::dropIfExists('coupons');
    }
};
