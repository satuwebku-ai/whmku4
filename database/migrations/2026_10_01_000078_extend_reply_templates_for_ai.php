<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_templates', function (Blueprint $table) {
            $table->string('category', 60)->nullable()->after('title');
            $table->boolean('is_active')->default(true)->after('body');
            // Dipakai sebagai bahan pengetahuan bot AI di Live Chat.
            $table->boolean('use_for_ai')->default(false)->after('is_active');
        });

        // Draf AI untuk email biasa tidak punya percakapan chat, jadi kolom
        // ini harus boleh kosong. "kind" membedakan balasan bot dan draf admin.
        Schema::table('ai_chat_usages', function (Blueprint $table) {
            $table->unsignedBigInteger('chat_conversation_id')->nullable()->change();
            $table->string('kind', 20)->default('bot')->after('model');
        });
    }

    public function down(): void
    {
        Schema::table('ai_chat_usages', function (Blueprint $table) {
            $table->dropColumn('kind');
        });

        Schema::table('mail_templates', function (Blueprint $table) {
            $table->dropColumn(['category', 'is_active', 'use_for_ai']);
        });
    }
};
