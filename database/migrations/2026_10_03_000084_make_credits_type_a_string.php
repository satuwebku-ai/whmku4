<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // enum tertutup menyulitkan menambah jenis mutasi baru (mis.
        // topup_reversal); daftar jenis sekarang dijaga di App\Models\Credit::TYPES.
        Schema::table('credits', function (Blueprint $table) {
            $table->string('type', 32)->change();
        });
    }

    public function down(): void
    {
        // Sengaja tidak mengembalikan ke enum: baris dengan jenis baru
        // akan membuat perubahan balik gagal.
    }
};
