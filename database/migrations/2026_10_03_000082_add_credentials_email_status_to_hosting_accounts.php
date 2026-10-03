<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hosting_accounts', function (Blueprint $table) {
            $table->timestamp('credentials_sent_at')->nullable();
            $table->timestamp('credentials_email_failed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('hosting_accounts', function (Blueprint $table) {
            $table->dropColumn(['credentials_sent_at', 'credentials_email_failed_at']);
        });
    }
};
