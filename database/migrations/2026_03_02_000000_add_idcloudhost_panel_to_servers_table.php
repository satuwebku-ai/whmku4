<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan "idcloudhost" ke enum panel -- beda dari cpanel/directadmin/
     * plesk, provider ini bukan panel di server yang sudah ada, tapi
     * menyediakan VM/VPS baru sepenuhnya lewat API on-demand. Tetap
     * dipasangkan ke tabel servers & IdCloudHostService supaya alur
     * provisioning otomatis (aktifkan/suspend/terminate) tetap konsisten
     * dengan panel hosting lain -- lihat HostingPanelInterface.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE servers MODIFY panel ENUM('cpanel', 'directadmin', 'plesk', 'idcloudhost') DEFAULT 'cpanel'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE servers MODIFY panel ENUM('cpanel', 'directadmin', 'plesk') DEFAULT 'cpanel'");
    }
};
