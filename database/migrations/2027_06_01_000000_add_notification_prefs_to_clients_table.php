<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Nomor WhatsApp dipisah dari kolom phone: nomor telepon kantor
            // belum tentu bisa menerima WhatsApp.
            $table->string('whatsapp_number')->nullable()->after('phone');

            // Email transaksional (invoice, tagihan, tiket) SELALU dikirim —
            // itu bagian dari layanan, bukan pilihan. Yang bisa dimatikan
            // klien hanya email promosi dan notifikasi WhatsApp.
            $table->boolean('notify_promo')->default(true)->after('whatsapp_number');
            $table->boolean('notify_whatsapp')->default(false)->after('notify_promo');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_number', 'notify_promo', 'notify_whatsapp']);
        });
    }
};
