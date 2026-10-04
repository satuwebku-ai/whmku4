<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Ganti email: alamat baru ditampung dulu sampai kodenya dikonfirmasi.
            $table->string('pending_email')->nullable();
            $table->string('pending_email_code_hash')->nullable();
            $table->timestamp('pending_email_expires_at')->nullable();
            $table->unsignedTinyInteger('pending_email_attempts')->default(0);

            // Klien memilih ganti password lewat kode OTP (email/WhatsApp).
            $table->boolean('password_otp_enabled')->default(false);

            // false = password akun ini acak (login Google) dan belum pernah
            // diatur klien sendiri, jadi tidak mungkin diminta "password saat ini".
            $table->boolean('password_set_by_user')->default(true);
        });

        DB::table('clients')->whereNotNull('google_id')->update(['password_set_by_user' => false]);
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'pending_email', 'pending_email_code_hash', 'pending_email_expires_at',
                'pending_email_attempts', 'password_otp_enabled', 'password_set_by_user',
            ]);
        });
    }
};
