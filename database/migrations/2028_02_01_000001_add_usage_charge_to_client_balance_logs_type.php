<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dipindah dari 2026_03_05_000000_add_deposit_billing_to_hosting_accounts.php
     * -- statement ini butuh tabel client_balance_logs, yang baru dibuat di
     * migration create_client_balance_system (tanggal yang sama, urutan file
     * sebelum ini). MySQL enum perlu dimodifikasi langsung karena Laravel
     * tidak punya cara "tambah nilai enum" bawaan.
     */
    public function up(): void
    {
        if (Schema::hasTable('client_balance_logs')) {
            DB::statement("ALTER TABLE client_balance_logs MODIFY type ENUM('topup', 'payment', 'refund', 'admin_adjustment', 'usage_charge') NOT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('client_balance_logs')) {
            DB::statement("ALTER TABLE client_balance_logs MODIFY type ENUM('topup', 'payment', 'refund', 'admin_adjustment') NOT NULL");
        }
    }
};
