<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();

            // Salah satu diisi: balasan dari staf (admin_id) atau klien (client_id).
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();

            $table->text('message');

            // Catatan internal hanya terlihat staf, tidak dikirim/ditampilkan ke klien.
            $table->boolean('is_internal_note')->default(false);

            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_replies');
    }
};
