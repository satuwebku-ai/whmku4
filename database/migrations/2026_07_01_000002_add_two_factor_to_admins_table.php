<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            // OTP dikirim lewat email — tidak butuh aplikasi authenticator
            // maupun paket tambahan, jadi bisa langsung dipakai.
            $table->boolean('two_factor_enabled')->default(false)->after('is_active');
            $table->string('otp_code_hash')->nullable()->after('two_factor_enabled');
            $table->timestamp('otp_expires_at')->nullable()->after('otp_code_hash');
            $table->unsignedTinyInteger('otp_attempts')->default(0)->after('otp_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn(['two_factor_enabled', 'otp_code_hash', 'otp_expires_at', 'otp_attempts']);
        });
    }
};
