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
            $table->unsignedInteger('port')->default(2087); // 2087 = WHM, 2222 = DirectAdmin, 8443 = Plesk
            $table->enum('panel', ['cpanel', 'directadmin', 'plesk'])->default('cpanel');
            $table->string('api_username'); // root / reseller username (WHM), admin (DA/Plesk)
            $table->text('api_token'); // API token / password — sebaiknya dienkripsi (lihat cast di Model)
            $table->boolean('verify_ssl')->default(true);
            $table->unsignedInteger('max_accounts')->nullable(); // kapasitas server, opsional
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_check_status')->nullable(); // "ok" atau pesan error terakhir
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
