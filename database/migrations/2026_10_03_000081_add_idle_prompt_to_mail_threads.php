<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kapan sistem mengirim email "masih perlu bantuan?". Kosong = belum.
        Schema::table('mail_threads', function (Blueprint $table) {
            $table->timestamp('idle_prompted_at')->nullable()->after('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::table('mail_threads', fn (Blueprint $t) => $t->dropColumn('idle_prompted_at'));
    }
};
