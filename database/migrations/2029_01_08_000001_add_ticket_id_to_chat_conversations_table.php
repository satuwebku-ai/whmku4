<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menandai satu percakapan live chat SUDAH pernah dijadikan tiket --
 * supaya tidak bisa dikonversi dua kali, dan supaya halaman chat bisa
 * menampilkan tautan langsung ke tiketnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->foreignId('ticket_id')->nullable()->after('assigned_at')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropForeign(['ticket_id']);
            $table->dropColumn('ticket_id');
        });
    }
};
