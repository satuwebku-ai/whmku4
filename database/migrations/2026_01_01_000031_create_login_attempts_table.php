<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();

            $table->enum('guard', ['admin', 'client'])->default('admin');

            // Identitas yang DICOBA — sengaja disimpan apa adanya (termasuk
            // yang tidak terdaftar), karena justru percobaan ke username
            // yang tidak ada itu tanda paling jelas ada yang menebak-nebak.
            $table->string('identifier');

            $table->boolean('successful')->default(false);

            // Alasan gagal: wrong_password, not_found, inactive, otp_failed,
            // captcha_failed, throttled
            $table->string('reason')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index(['guard', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            $table->index(['successful', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
