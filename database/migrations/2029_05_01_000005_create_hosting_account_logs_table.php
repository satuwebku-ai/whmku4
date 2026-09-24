<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jejak audit KHUSUS satu hosting account (suspend/unsuspend/upgrade/
     * ganti paket/dst) -- beda tujuan dengan activity_logs yang sifatnya
     * feed notifikasi umum untuk klien/admin, bukan audit trail per-akun.
     */
    public function up(): void
    {
        Schema::create('hosting_account_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hosting_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action'); // suspend, unsuspend, upgrade, terminate, dll
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hosting_account_logs');
    }
};
