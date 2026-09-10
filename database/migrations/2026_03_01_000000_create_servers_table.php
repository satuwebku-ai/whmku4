<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // label internal, mis. "Server JKT-01"
            $table->string('hostname'); // mis. server1.contoh.com atau IP
            // Nameserver server hosting ini — dipakai otomatis mengarahkan
            // domain begitu klien beli hosting untuk domain yang sudah
            // terdaftar di sistem kita (lihat ProvisioningService::provisionHosting()).
            $table->string('ns1')->nullable();
            $table->string('ns2')->nullable();
            $table->unsignedInteger('port')->default(2087); // 2087 = WHM, 2222 = DirectAdmin, 8443 = Plesk
            // idcloudhost: beda dari cpanel/directadmin/plesk, provider ini
            // bukan panel di server yang sudah ada, tapi menyediakan VM/VPS
            // baru sepenuhnya lewat API on-demand. Tetap dipasangkan ke
            // tabel servers & IdCloudHostService supaya alur provisioning
            // otomatis (aktifkan/suspend/terminate) tetap konsisten dengan
            // panel hosting lain -- lihat HostingPanelInterface.
            $table->enum('panel', ['cpanel', 'directadmin', 'plesk', 'idcloudhost'])->default('cpanel');
            $table->string('api_username'); // root / reseller username (WHM), admin (DA/Plesk)
            $table->text('api_token'); // API token / password — sebaiknya dienkripsi (lihat cast di Model)
            $table->boolean('verify_ssl')->default(true);
            $table->unsignedInteger('max_accounts')->nullable(); // kapasitas server, opsional

            // Kartu harga per komponen -- ditempel di SERVER (bukan
            // global), supaya tiap provider cloud punya tarifnya
            // sendiri-sendiri. Satuan harga semuanya "per jam", konsisten
            // dengan cara ChargeHourlyUsage menghitung.
            $table->decimal('price_per_vcpu_hour', 12, 6)->nullable();
            $table->decimal('price_per_ram_gb_hour', 12, 6)->nullable();
            $table->decimal('price_per_storage_gb_hour', 12, 6)->nullable();
            $table->decimal('price_per_backup_gb_hour', 12, 6)->nullable();
            $table->decimal('price_per_snapshot_gb_hour', 12, 6)->nullable();
            $table->decimal('price_windows_license_per_vcpu_hour', 12, 6)->nullable();

            // Alternatif pengisian kartu harga: alih-alih mengetik harga
            // jual tiap komponen satu per satu, admin cukup menentukan
            // MARKUP PERSEN di atas harga modal yang ditarik langsung dari
            // API provider (/pricing/policy).
            $table->enum('pricing_mode', ['manual', 'markup'])->default('manual');
            $table->decimal('markup_percent', 6, 2)->nullable();
            $table->json('cost_cache')->nullable();
            $table->timestamp('cost_cached_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_check_status')->nullable(); // "ok" atau pesan error terakhir
            $table->timestamps();
        });

        // hosting_accounts dibuat sebelum servers (migration ini), jadi
        // kolom server_id-nya dibuat tanpa FK saat itu -- constraint-nya
        // dipasang di sini.
        Schema::table('hosting_accounts', function (Blueprint $table) {
            $table->foreign('server_id')->references('id')->on('servers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hosting_accounts', function (Blueprint $table) {
            $table->dropForeign(['server_id']);
        });

        Schema::dropIfExists('servers');
    }
};
