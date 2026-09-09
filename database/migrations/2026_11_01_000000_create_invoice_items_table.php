<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sebelum ini, satu invoice hanya bisa terkait SATU order (invoices.order_id).
     * Itu cukup untuk invoice manual, tapi checkout keranjang (Fase 7c) bisa
     * berisi beberapa item sekaligus (mis. hosting + domain) yang harus
     * tertagih dalam SATU invoice. invoice_items menampung rincian per item;
     * invoices.order_id tetap ada untuk kompatibilitas invoice lama yang
     * hanya py 1 order.
     */
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
