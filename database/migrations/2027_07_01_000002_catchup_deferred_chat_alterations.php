<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dipindah dari:
     * - 2026_03_09_000000_create_ai_chat_usages_table.php (FK ke chat_conversations)
     * - 2026_03_10_000000_add_channel_to_chat_conversations_table.php (kolom channel)
     * Keduanya butuh chat_conversations yang baru dibuat di file ini
     * (2027_07_01_000000_create_chat_tables.php).
     */
    public function up(): void
    {
        if (Schema::hasTable('ai_chat_usages') && ! $this->hasForeignKey('ai_chat_usages', 'ai_chat_usages_chat_conversation_id_foreign')) {
            Schema::table('ai_chat_usages', function (Blueprint $table) {
                $table->foreign('chat_conversation_id')->references('id')->on('chat_conversations')->cascadeOnDelete();
            });
        }

        if (! Schema::hasColumn('chat_conversations', 'channel')) {
            Schema::table('chat_conversations', function (Blueprint $table) {
                $table->enum('channel', ['web', 'whatsapp'])->default('web')->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('chat_conversations', 'channel')) {
            Schema::table('chat_conversations', function (Blueprint $table) {
                $table->dropColumn('channel');
            });
        }

        if (Schema::hasTable('ai_chat_usages') && $this->hasForeignKey('ai_chat_usages', 'ai_chat_usages_chat_conversation_id_foreign')) {
            Schema::table('ai_chat_usages', function (Blueprint $table) {
                $table->dropForeign('ai_chat_usages_chat_conversation_id_foreign');
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
