<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('avatar')->nullable();
            // Role operasional: superadmin, administrator, finance, billing,
            // domain_manager, hosting_manager, support, marketing, developer,
            // devops, auditor, viewer. admin/staff tetap diterima sebagai alias lama.
            $table->string('role')->default('administrator');
            // Daftar modul (array JSON) yang boleh diakses admin ini, mis.
            // ["billing","support"]. NULL berarti belum diatur manual oleh
            // superadmin -> dipakai daftar bawaan sesuai peran (lihat
            // Admin::ROLE_DEFAULT_MODULES). Array kosong [] berarti sengaja
            // dikunci total dari semua modul.
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            // OTP dikirim lewat email — tidak butuh aplikasi authenticator
            // maupun paket tambahan.
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('otp_code_hash')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->unsignedTinyInteger('otp_attempts')->default(0);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
