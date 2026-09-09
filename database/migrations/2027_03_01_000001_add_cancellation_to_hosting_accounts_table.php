<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hosting_accounts', function (Blueprint $table) {
            // Pembatalan tidak langsung mematikan layanan — klien mengajukan,
            // admin meninjau dan memprosesnya. Kolom status yang sudah ada
            // (pending/active/suspended/terminated) sengaja tidak disentuh
            // sampai admin menyetujui, supaya layanan tidak berhenti sendiri
            // hanya karena klien salah klik.
            $table->enum('cancellation_status', ['none', 'requested', 'approved', 'declined'])
                  ->default('none')->after('status');
            $table->text('cancellation_reason')->nullable()->after('cancellation_status');
            $table->timestamp('cancellation_requested_at')->nullable()->after('cancellation_reason');
            $table->text('cancellation_admin_note')->nullable()->after('cancellation_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('hosting_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'cancellation_status', 'cancellation_reason',
                'cancellation_requested_at', 'cancellation_admin_note',
            ]);
        });
    }
};
