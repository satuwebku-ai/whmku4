<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // notify_whatsapp baru dibuat belakangan (2027_06_01) -- kalau
        // belum ada, tunda ke catch-up migration
        // 2027_06_01_000002_catchup_add_notify_sms_to_clients_table.php
        if (! Schema::hasColumn('clients', 'notify_whatsapp') || Schema::hasColumn('clients', 'notify_sms')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table) {
            // SMS dikirim ke kolom `phone` yang sudah ada -- beda dari
            // WhatsApp, SMS tidak butuh nomor terpisah karena berlaku ke
            // semua nomor seluler biasa.
            $table->boolean('notify_sms')->default(false)->after('notify_whatsapp');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('notify_sms');
        });
    }
};
