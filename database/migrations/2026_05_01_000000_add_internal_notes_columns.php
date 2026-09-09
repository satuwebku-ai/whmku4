<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->text('internal_notes')->nullable()->after('status');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->text('internal_notes')->nullable()->after('status');
        });

        Schema::table('hosting_accounts', function (Blueprint $table) {
            $table->text('internal_notes')->nullable()->after('provision_message');
        });

        Schema::table('domains', function (Blueprint $table) {
            $table->text('internal_notes')->nullable()->after('provision_message');
        });

        // Tabel invoices sudah punya kolom "notes" dari Fase 2, tidak perlu ditambah.
    }

    public function down(): void
    {
        Schema::table('clients', fn (Blueprint $table) => $table->dropColumn('internal_notes'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('internal_notes'));
        Schema::table('hosting_accounts', fn (Blueprint $table) => $table->dropColumn('internal_notes'));
        Schema::table('domains', fn (Blueprint $table) => $table->dropColumn('internal_notes'));
    }
};
