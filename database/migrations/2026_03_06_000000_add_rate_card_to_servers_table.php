<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kartu harga per komponen -- ditempel di SERVER (bukan global),
     * supaya tiap provider cloud (IDCloudHost sekarang, provider lain
     * nanti) punya tarifnya sendiri-sendiri, tidak tercampur. Kalau
     * nanti nambah provider kedua dengan harga CPU beda, server itu
     * cukup diisi kartu harganya sendiri -- server IDCloudHost yang
     * sudah ada tidak ikut berubah.
     *
     * Satuan harga semuanya "per jam" (bukan per bulan), supaya
     * konsisten dengan cara ChargeHourlyUsage menghitung.
     */
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->decimal('price_per_vcpu_hour', 12, 6)->nullable()->after('max_accounts');
            $table->decimal('price_per_ram_gb_hour', 12, 6)->nullable()->after('price_per_vcpu_hour');
            $table->decimal('price_per_storage_gb_hour', 12, 6)->nullable()->after('price_per_ram_gb_hour');
            $table->decimal('price_per_backup_gb_hour', 12, 6)->nullable()->after('price_per_storage_gb_hour');
            $table->decimal('price_per_snapshot_gb_hour', 12, 6)->nullable()->after('price_per_backup_gb_hour');
            $table->decimal('price_windows_license_per_vcpu_hour', 12, 6)->nullable()->after('price_per_snapshot_gb_hour');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn([
                'price_per_vcpu_hour', 'price_per_ram_gb_hour', 'price_per_storage_gb_hour',
                'price_per_backup_gb_hour', 'price_per_snapshot_gb_hour', 'price_windows_license_per_vcpu_hour',
            ]);
        });
    }
};
