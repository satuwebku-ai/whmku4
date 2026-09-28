<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Invoice, order, dan payment adalah catatan keuangan. Foreign key
     * cascade/null di database membuat "hapus" mereka diam-diam menghapus
     * atau memutus tabel lain, jadi ketiganya diubah ke soft delete: baris
     * tetap ada (nomor, jumlah, tautan) dan bisa dipulihkan lewat
     * `php artisan records:restore`.
     */
    private array $tables = ['invoices', 'orders', 'payments'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->softDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropSoftDeletes();
                });
            }
        }
    }
};
