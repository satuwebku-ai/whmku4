<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 2FA untuk klien -- skema sama persis dengan admins (lihat
     * 2026_07_01_000002_add_two_factor_to_admins_table.php), sudah
     * terbukti berfungsi baik di sana. OTP dikirim lewat email, tidak
     * butuh aplikasi authenticator terpisah.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('two_factor_enabled')->default(false)->after('status');
            $table->string('otp_code_hash')->nullable()->after('two_factor_enabled');
            $table->timestamp('otp_expires_at')->nullable()->after('otp_code_hash');
            $table->unsignedTinyInteger('otp_attempts')->default(0)->after('otp_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['two_factor_enabled', 'otp_code_hash', 'otp_expires_at', 'otp_attempts']);
        });
    }
};
