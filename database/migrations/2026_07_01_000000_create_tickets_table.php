<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();   // TKT-2026-0001
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('admins')->nullOnDelete();

            $table->string('subject');
            $table->string('department')->default('support'); // support, billing, sales, abuse
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');

            // open     : tiket baru dari klien, belum dibalas staf
            // answered : staf sudah membalas, menunggu respons klien
            // customer_reply : klien membalas lagi, perlu ditindaklanjuti
            // closed   : selesai
            $table->enum('status', ['open', 'answered', 'customer_reply', 'closed'])->default('open');

            // Relasi opsional ke layanan yang dikeluhkan
            $table->foreignId('hosting_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('domain_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('last_reply_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'last_reply_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
