<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrars', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // label internal, mis. "Namecheap - Utama"
            $table->string('provider')->default('namecheap'); // namecheap, resellbiz — provider lain menyusul
            $table->string('api_username');
            $table->text('api_key'); // dienkripsi
            $table->string('username')->nullable(); // Namecheap: UserName (biasanya sama dgn ApiUser)
            $table->string('client_ip')->nullable(); // Namecheap wajib whitelist IP client
            $table->boolean('sandbox')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_check_status')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrars');
    }
};
