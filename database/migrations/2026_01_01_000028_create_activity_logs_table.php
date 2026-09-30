<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // order, payment, ticket, client, domain, service, system
            $table->string('type')->index();
            $table->string('title');
            $table->text('description')->nullable();

            // Tautan ke halaman terkait supaya admin bisa langsung menuju
            // objeknya dari daftar aktivitas.
            $table->string('link')->nullable();

            $table->string('icon')->nullable();
            $table->string('level')->default('info'); // info, success, warning, danger

            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();

            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['read_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
