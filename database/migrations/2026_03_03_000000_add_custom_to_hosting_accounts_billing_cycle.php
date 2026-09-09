<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * BUG KRITIS: kolom products.custom_cycle_days (siklus "Custom")
     * sudah ada sejak migrasi sebelumnya, tapi enum billing_cycle di
     * hosting_accounts TIDAK PERNAH diperbarui untuk menerima nilai
     * 'custom' -- akibatnya checkout GAGAL TOTAL (SQLSTATE: Data
     * truncated for column 'billing_cycle') setiap kali klien membeli
     * produk dengan siklus tagihan Custom.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE hosting_accounts MODIFY billing_cycle ENUM('monthly', 'quarterly', 'semi_annually', 'annually', 'custom') DEFAULT 'monthly'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE hosting_accounts MODIFY billing_cycle ENUM('monthly', 'quarterly', 'semi_annually', 'annually') DEFAULT 'monthly'");
    }
};
