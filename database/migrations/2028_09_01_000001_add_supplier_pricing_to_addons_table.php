<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            // Harga modal disimpan terpisah dari harga jual. Harga jual lama
            // tetap menjadi sumber harga checkout dan renewal.
            $table->decimal('cost_price_monthly', 12, 2)->nullable()->after('price_monthly');
            $table->decimal('cost_price_quarterly', 12, 2)->nullable()->after('price_quarterly');
            $table->decimal('cost_price_semi_annually', 12, 2)->nullable()->after('price_semi_annually');
            $table->decimal('cost_price_annually', 12, 2)->nullable()->after('price_annually');

            $table->string('pricing_source')->default('manual')->after('sort_order');
            $table->boolean('is_public')->default(true)->after('pricing_source');
            $table->string('supplier_api_url')->nullable()->after('is_public');
            $table->string('supplier_http_method')->default('GET')->after('supplier_api_url');
            $table->text('supplier_api_token')->nullable()->after('supplier_http_method');
            $table->string('supplier_price_path_monthly')->nullable()->after('supplier_api_token');
            $table->string('supplier_price_path_quarterly')->nullable()->after('supplier_price_path_monthly');
            $table->string('supplier_price_path_semi_annually')->nullable()->after('supplier_price_path_quarterly');
            $table->string('supplier_price_path_annually')->nullable()->after('supplier_price_path_semi_annually');
            $table->timestamp('supplier_last_synced_at')->nullable()->after('supplier_price_path_annually');
            $table->text('supplier_last_error')->nullable()->after('supplier_last_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            $table->dropColumn([
                'cost_price_monthly',
                'cost_price_quarterly',
                'cost_price_semi_annually',
                'cost_price_annually',
                'pricing_source',
                'is_public',
                'supplier_api_url',
                'supplier_http_method',
                'supplier_api_token',
                'supplier_price_path_monthly',
                'supplier_price_path_quarterly',
                'supplier_price_path_semi_annually',
                'supplier_price_path_annually',
                'supplier_last_synced_at',
                'supplier_last_error',
            ]);
        });
    }
};