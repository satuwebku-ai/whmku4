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
        // Idempotent: migration ini pernah gagal di tengah jalan (statement
        // client_balance_logs di bawah error karena tabelnya belum ada saat
        // itu), sementara ALTER TABLE hosting_accounts di atasnya sudah
        // sempat kebentuk (MySQL DDL tidak transactional). Cek hasColumn
        // supaya aman dijalankan ulang.
        Schema::table('hosting_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('hosting_accounts', 'billing_mode')) {
                $table->enum('billing_mode', ['invoice', 'deposit'])->default('invoice')->after('billing_cycle');
            }
            if (! Schema::hasColumn('hosting_accounts', 'hourly_rate')) {
                $table->decimal('hourly_rate', 12, 4)->nullable()->after('billing_mode');
            }
            if (! Schema::hasColumn('hosting_accounts', 'last_billed_at')) {
                $table->timestamp('last_billed_at')->nullable()->after('hourly_rate');
            }
        });

        // Catatan: penambahan nilai 'usage_charge' ke enum client_balance_logs.type
        // dipindah ke migration 2028_02_01_000001_add_usage_charge_to_client_balance_logs_type.php
        // karena tabel client_balance_logs baru dibuat belakangan (2028_02_01),
        // jauh setelah migration ini.
    }

    public function down(): void
    {
        Schema::table('hosting_accounts', function (Blueprint $table) {
            $table->dropColumn(['billing_mode', 'hourly_rate', 'last_billed_at']);
        });
    }
};