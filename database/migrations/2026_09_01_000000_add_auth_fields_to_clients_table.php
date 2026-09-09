<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->timestamp('last_login_at')->nullable()->after('internal_notes');
            $table->string('last_login_ip')->nullable()->after('last_login_at');
            $table->rememberToken()->after('last_login_ip');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['email_verified_at', 'last_login_at', 'last_login_ip', 'remember_token']);
        });
    }
};
