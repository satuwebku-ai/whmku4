<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Hapus konfigurasi scheduler lama yang mungkin sudah dibuat oleh
     * versi aplikasi sebelum fitur trial dihapus.
     */
    public function up(): void
    {
        DB::table('cron_jobs')
            ->where('key', 'expire_trials')
            ->delete();
    }

    public function down(): void
    {
        // Fitur trial sudah dihapus; jangan membuat kembali job obsolete.
    }
};