<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique(); // INV-2026-0001
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->enum('status', ['unpaid', 'paid', 'overdue', 'cancelled'])->default('unpaid');
            // Menandai invoice ini murni "isi ulang saldo", bukan tagihan
            // layanan/domain — supaya hook pembayaran tahu harus menambah
            // saldo klien, bukan menjalankan provisioning seperti biasa.
            $table->boolean('is_topup')->default(false);
            $table->date('issue_date');
            $table->date('due_date');
            $table->date('paid_at')->nullable();
            $table->string('payment_method')->nullable(); // diisi manual di Fase 2, otomatis di Fase 5
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // hosting_accounts dibuat sebelum invoices (migration ini), jadi
        // kolom renewal_invoice_id & pending_upgrade_invoice_id dibuat
        // tanpa FK saat itu -- constraint-nya dipasang di sini.
        Schema::table('hosting_accounts', function (Blueprint $table) {
            $table->foreign('renewal_invoice_id')->references('id')->on('invoices')->nullOnDelete();
            $table->foreign('pending_upgrade_invoice_id')->references('id')->on('invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hosting_accounts', function (Blueprint $table) {
            $table->dropForeign(['renewal_invoice_id']);
            $table->dropForeign(['pending_upgrade_invoice_id']);
        });

        Schema::dropIfExists('invoices');
    }
};
