<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * hosting_accounts.domain cuma satu string (domain utama). Tabel ini
     * untuk domain TAMBAHAN (addon/parked domain) yang menumpang di satu
     * hosting account yang sama -- fitur cPanel "Addon Domains".
     */
    public function up(): void
    {
        Schema::create('hosting_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hosting_account_id')->constrained()->cascadeOnDelete();
            $table->string('domain_name');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['hosting_account_id', 'domain_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hosting_domains');
    }
};
