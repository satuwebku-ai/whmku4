<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('balance', 14, 2)->default(0)->after('status');
        });

        Schema::table('invoices', function (Blueprint $table) {
            // Menandai invoice ini murni "isi ulang saldo", bukan tagihan
            // layanan/domain — supaya hook pembayaran tahu harus menambah
            // saldo klien, bukan menjalankan provisioning seperti biasa.
            $table->boolean('is_topup')->default(false)->after('status');
        });

        // Buku besar (ledger) — setiap perubahan saldo klien tercatat di
        // sini, baik nambah (top up, refund) maupun berkurang (dipakai
        // bayar invoice, potongan admin). Tanpa ini, saldo cuma jadi
        // satu angka tanpa jejak — tidak bisa diaudit kalau ada yang
        // komplain "kok saldo saya berkurang?".
        Schema::create('client_balance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            // Positif = saldo bertambah, negatif = saldo berkurang.
            // Satu kolom bertanda lebih sederhana daripada kolom
            // arah+nominal terpisah, dan tetap mudah dibaca di riwayat.
            $table->decimal('amount', 14, 2);

            $table->enum('type', ['topup', 'payment', 'refund', 'admin_adjustment']);
            $table->string('description');
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('balance_after', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_balance_logs');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('is_topup');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('balance');
        });
    }
};
