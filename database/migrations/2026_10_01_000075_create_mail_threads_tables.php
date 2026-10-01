<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Inbox Email: satu thread = satu rangkaian surat dengan satu lawan
        // bicara (contact_email). Terpisah dari Live Chat dan tiket.
        Schema::create('mail_threads', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->string('contact_email')->index();
            $table->string('contact_name')->nullable();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('open')->index();   // open | closed
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('mail_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mail_thread_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 3);                            // in | out
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->string('to_email');
            $table->string('subject')->nullable();
            $table->longText('body');
            $table->string('message_id')->nullable()->index();         // Message-ID tanpa <>
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->json('attachments')->nullable();                   // [{path,name,mime,size}]
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_messages');
        Schema::dropIfExists('mail_threads');
    }
};
