<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fondasi generik untuk layanan yang ditagih PER JAM dari saldo
     * (deposit) -- BUKAN lewat invoice bulanan seperti hosting biasa.
     * Sengaja dibuat generik (bukan khusus kolom "vm_*") supaya nanti
     * modul VM/VPS tinggal mengisi billing_mode='deposit' +
     * hourly_rate begitu dibangun, tanpa perlu migrasi tambahan lagi.
     */
    public function up(): void
    {
        Schema::table('hosting_accounts', function (Blueprint $table) {
            $table->enum('billing_mode', ['invoice', 'deposit'])->default('invoice')->after('billing_cycle');
            $table->decimal('hourly_rate', 12, 4)->nullable()->after('billing_mode');
            $table->timestamp('last_billed_at')->nullable()->after('hourly_rate');
        });

        // Tipe baru di ledger saldo -- MySQL enum perlu dimodifikasi
        // langsung (Laravel tidak punya cara "tambah nilai enum" bawaan).
        DB::statement("ALTER TABLE client_balance_logs MODIFY type ENUM('topup', 'payment', 'refund', 'admin_adjustment', 'usage_charge') NOT NULL");
    }

    public function down(): void
    {
        Schema::table('hosting_accounts', function (Blueprint $table) {
            $table->dropColumn(['billing_mode', 'hourly_rate', 'last_billed_at']);
        });

        DB::statement("ALTER TABLE client_balance_logs MODIFY type ENUM('topup', 'payment', 'refund', 'admin_adjustment') NOT NULL");
    }
};
