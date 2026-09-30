<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cron_jobs', function (Blueprint $table) {
            $table->id();

            // Kunci tetap yang menautkan baris ini ke tugas bawaan di kode.
            // Tidak bisa diubah dari UI supaya tautannya tidak putus.
            $table->string('key')->unique();

            $table->string('name');
            $table->text('description')->nullable();

            // Perintah artisan yang dijalankan.
            $table->string('command');

            // Jeda antar-jalan dalam menit. Dipakai daripada ekspresi cron
            // supaya lebih mudah dipahami dan sulit diisi salah.
            $table->unsignedInteger('interval_minutes')->default(1440);

            $table->boolean('is_enabled')->default(true);

            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->unsignedInteger('run_count')->default(0);

            // Hasil jalan terakhir, untuk ditampilkan di panel.
            $table->string('last_status')->nullable(); // success, failed, running
            $table->text('last_output')->nullable();
            $table->unsignedInteger('last_duration_ms')->nullable();

            $table->timestamps();

            $table->index(['is_enabled', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_jobs');
    }
};
