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
            // Produk katalog yang dipesan — dipakai saat provisioning untuk
            // tahu server tujuan & nama package cPanel. products baru
            // dibuat belakangan (2026_10_01) -- FK dipasang di
            // 2026_10_01_000001_create_products_table.php.
            $table->foreignId('product_id')->nullable();
            $table->foreignId('hosting_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name'); // mis. "Cloud Hosting - Pro", "Domain .com"
            $table->enum('order_type', ['hosting', 'domain', 'vps', 'other'])->default('hosting');
            $table->decimal('amount', 12, 2)->default(0);
            // Canonical lifecycle; legacy values are intentionally not part
            // of a fresh schema.
            $table->string('status', 40)->default('draft');
            $table->enum('stock_reservation_status', ['none', 'reserved', 'consumed', 'released'])->default('none')->index();
            $table->timestamp('provisioning_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
