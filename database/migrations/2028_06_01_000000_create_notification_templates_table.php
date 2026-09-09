<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuma menyimpan template yang SUDAH diubah admin — bukan salinan
     * semua 13 template sejak awal. Kalau baris untuk suatu key tidak
     * ada di sini, sistem otomatis memakai kata-kata bawaan yang sudah
     * ada di kode (lihat NotificationTemplate::defaults()). Ini sengaja:
     * kalau nanti saya perbaiki/tambah kata-kata bawaan lewat update
     * kode, situs yang belum pernah menyentuh pengaturan ini otomatis
     * ikut ter-update — bukan macet di salinan lama yang ikut ter-seed
     * saat migrasi pertama kali.
     */
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('subject')->nullable();
            $table->text('body_mail')->nullable();
            $table->text('body_whatsapp')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
