<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique(); // ORD-1042
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hosting_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name'); // mis. "Cloud Hosting - Pro", "Domain .com"
            $table->enum('order_type', ['hosting', 'domain', 'vps', 'other'])->default('hosting');
            $table->decimal('amount', 12, 2)->default(0);
            $table->enum('status', ['pending', 'active', 'suspended', 'cancelled'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
