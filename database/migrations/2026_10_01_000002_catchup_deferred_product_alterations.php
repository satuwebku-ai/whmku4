<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dipindah dari 2026_03_08_000000_add_type_to_categories_and_billing_mode_to_products.php
     * -- migrasi itu mengubah product_categories & products sebelum kedua
     * tabel itu ada (baru dibuat di file ini, 2026_10_01). Dijaga
     * hasColumn() supaya aman dijalankan ulang.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('product_categories', 'type')) {
            Schema::table('product_categories', function (Blueprint $table) {
                $table->enum('type', ['hosting', 'vps'])->default('hosting')->after('slug');
            });
        }

        if (! Schema::hasColumn('products', 'billing_mode')) {
            Schema::table('products', function (Blueprint $table) {
                $table->enum('billing_mode', ['invoice', 'deposit'])->default('invoice')->after('panel_package');
            });
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('panel_package', 500)->nullable()->change();
        });

        // FK product_option_groups.product_id juga ditunda ke sini --
        // tabelnya dibuat lebih dulu (2026_09_04) tapi products belum ada
        // saat itu, jadi kolomnya dibuat tanpa constraint di migrasi asli.
        if (Schema::hasTable('product_option_groups') && ! $this->hasForeignKey('product_option_groups', 'product_option_groups_product_id_foreign')) {
            Schema::table('product_option_groups', function (Blueprint $table) {
                $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_option_groups') && $this->hasForeignKey('product_option_groups', 'product_option_groups_product_id_foreign')) {
            Schema::table('product_option_groups', function (Blueprint $table) {
                $table->dropForeign('product_option_groups_product_id_foreign');
            });
        }

        if (Schema::hasColumn('products', 'billing_mode')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('billing_mode');
            });
        }

        if (Schema::hasColumn('product_categories', 'type')) {
            Schema::table('product_categories', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }

    private function hasForeignKey(string $table, string $constraintName): bool
    {
        $conn = Schema::getConnection();
        $dbName = $conn->getDatabaseName();

        return $conn->table('information_schema.table_constraints')
            ->where('table_schema', $dbName)
            ->where('table_name', $table)
            ->where('constraint_name', $constraintName)
            ->exists();
    }
};
