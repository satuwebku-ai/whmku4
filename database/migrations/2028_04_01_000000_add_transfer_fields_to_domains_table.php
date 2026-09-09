<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            // Membedakan "domain baru didaftarkan" dari "domain dipindah
            // dari registrar lain" — dua-duanya sama-sama masuk tabel
            // domains, tapi ProvisioningService perlu tahu API mana yang
            // dipanggil (registerDomain vs transferDomain).
            $table->boolean('is_transfer')->default(false)->after('provision_status');

            // Kode EPP/Auth dari registrar lama — dienkripsi karena setara
            // password sekali pakai untuk memindahkan domain, sama seperti
            // client_details di hosting_accounts.
            $table->text('transfer_auth_code')->nullable()->after('is_transfer');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn(['is_transfer', 'transfer_auth_code']);
        });
    }
};
