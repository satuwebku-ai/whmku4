<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dipindah dari 2026_09_05_000000_add_notify_sms_to_clients_table.php
     * -- memakai ->after('notify_whatsapp'), padahal kolom notify_whatsapp
     * baru dibuat di 2027_06_01_000000_add_notification_prefs_to_clients_table.php.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('clients', 'notify_sms')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->boolean('notify_sms')->default(false)->after('notify_whatsapp');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('clients', 'notify_sms')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropColumn('notify_sms');
            });
        }
    }
};
