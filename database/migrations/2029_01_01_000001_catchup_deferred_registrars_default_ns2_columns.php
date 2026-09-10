<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dipindah dari:
     * - 2026_09_01_000100_add_whois_privacy_price_to_registrars.php
     * - 2026_09_05_000003_add_documents_email_to_registrars_table.php
     * - blok registrars di 2027_07_01_000000_ensure_whois_privacy_columns_exist.php
     * Ketiganya memakai ->after('default_ns2'), padahal kolom default_ns2
     * baru dibuat di file ini (2029_01_01).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('registrars', 'whois_privacy_price')) {
            Schema::table('registrars', function (Blueprint $table) {
                $table->decimal('whois_privacy_price', 12, 2)->nullable()->after('default_ns2');
            });
        }

        if (! Schema::hasColumn('registrars', 'documents_email')) {
            Schema::table('registrars', function (Blueprint $table) {
                $table->string('documents_email')->nullable()->after('whois_privacy_price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('registrars', 'documents_email')) {
            Schema::table('registrars', function (Blueprint $table) {
                $table->dropColumn('documents_email');
            });
        }

        if (Schema::hasColumn('registrars', 'whois_privacy_price')) {
            Schema::table('registrars', function (Blueprint $table) {
                $table->dropColumn('whois_privacy_price');
            });
        }
    }
};
