<?php

use App\Services\Billing\InvoiceItemServiceBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Setiap baris invoice menyimpan LAYANAN yang ditagih (domain/hosting)
     * dan jenis tagihannya, seperti relid di WHMCS. Sebelumnya hubungan
     * hanya satu arah (domains.renewal_invoice_id) dan dikosongkan setelah
     * lunas, sehingga invoice lama tidak bisa dilacak ke layanannya.
     */
    public function up(): void
    {
        if (! Schema::hasTable('invoice_items') || Schema::hasColumn('invoice_items', 'service_type')) {
            return;
        }

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('service_type', 20)->nullable()->after('order_id');
            $table->unsignedBigInteger('service_id')->nullable()->after('service_type');
            $table->string('item_type', 30)->nullable()->after('service_id');
            $table->index(['service_type', 'service_id'], 'invoice_items_service_index');
        });

        // Isi otomatis untuk invoice lama. Kegagalan di sini tidak boleh
        // menggagalkan migrasi; bisa diulang: php artisan lumora:backfill-invoice-services
        try {
            app(InvoiceItemServiceBackfill::class)->run();
        } catch (Throwable $e) {
            Log::warning('Backfill layanan invoice gagal saat migrasi: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoice_items') || ! Schema::hasColumn('invoice_items', 'service_type')) {
            return;
        }

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropIndex('invoice_items_service_index');
            $table->dropColumn(['service_type', 'service_id', 'item_type']);
        });
    }
};
