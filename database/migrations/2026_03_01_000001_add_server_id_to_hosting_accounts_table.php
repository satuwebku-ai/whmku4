<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hosting_accounts', function (Blueprint $table) {
            $table->foreignId('server_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
            $table->string('provision_status')->default('manual')->after('status'); // manual, provisioned, failed
            $table->text('provision_message')->nullable()->after('provision_status'); // pesan sukses/error terakhir dari API panel
        });
    }

    public function down(): void
    {
        Schema::table('hosting_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('server_id');
            $table->dropColumn(['provision_status', 'provision_message']);
        });
    }
};
