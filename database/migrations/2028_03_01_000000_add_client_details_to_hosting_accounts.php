<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Untuk layanan yang provisioning-nya manual (VPS, dedicated server,
     * lisensi software, dst — apa pun yang server_id-nya kosong), tidak
     * ada cara klien melihat info aksesnya sama sekali. Kolom bebas ini
     * diisi admin sendiri setelah setup manual di luar sistem (IP, root
     * password, URL panel VPS, dll), lalu ditampilkan langsung ke klien.
     *
     * Dienkripsi karena isinya sering berupa kredensial sensitif — pola
     * yang sama dipakai untuk api_token Server dan kredensial gateway.
     */
    public function up(): void
    {
        Schema::table('hosting_accounts', function (Blueprint $table) {
            $table->text('client_details')->nullable()->after('provision_message');
        });
    }

    public function down(): void
    {
        Schema::table('hosting_accounts', function (Blueprint $table) {
            $table->dropColumn('client_details');
        });
    }
};
