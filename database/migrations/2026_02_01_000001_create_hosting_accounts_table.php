<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hosting_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->string('package'); // nama paket, mis. "Cloud Hosting - Pro"
            $table->string('server')->nullable(); // nama server, akan jadi relasi ke tabel servers di Fase 3
            $table->string('panel')->default('cpanel'); // cpanel, directadmin, plesk — dipakai di Fase 3
            $table->string('username')->nullable(); // username akun di panel hosting
            $table->decimal('price', 12, 2)->default(0);
            $table->enum('billing_cycle', ['monthly', 'quarterly', 'semi_annually', 'annually'])->default('monthly');
            $table->enum('status', ['pending', 'active', 'suspended', 'terminated'])->default('pending');
            $table->date('next_due_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hosting_accounts');
    }
};
