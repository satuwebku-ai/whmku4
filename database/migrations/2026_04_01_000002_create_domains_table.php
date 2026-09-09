<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('registrar_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tld_id')->nullable()->constrained()->nullOnDelete();
            $table->string('domain_name'); // domain lengkap, mis. "contoh.com"
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedTinyInteger('years')->default(1);
            $table->enum('status', ['pending', 'active', 'expired', 'cancelled'])->default('pending');
            $table->date('register_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->boolean('whois_privacy')->default(false);
            $table->json('nameservers')->nullable();
            $table->string('provision_status')->default('manual'); // manual, registered, failed
            $table->text('provision_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
