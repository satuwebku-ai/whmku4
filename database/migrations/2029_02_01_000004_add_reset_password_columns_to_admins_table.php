<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin sebelumnya tidak punya alur lupa password sama sekali --
     * cuma Client yang punya (lihat clients.reset_code_hash dkk). Kolom
     * ini menyusul persis pola yang sama supaya
     * App\Http\Controllers\Auth\Admin\ForgotPasswordController bisa
     * dibuat konsisten dengan punya Client.
     */
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->string('reset_code_hash')->nullable()->after('otp_attempts');
            $table->timestamp('reset_code_expires_at')->nullable()->after('reset_code_hash');
            $table->unsignedTinyInteger('reset_attempts')->default(0)->after('reset_code_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn(['reset_code_hash', 'reset_code_expires_at', 'reset_attempts']);
        });
    }
};
