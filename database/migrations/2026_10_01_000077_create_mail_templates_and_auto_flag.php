<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_messages', function (Blueprint $table) {
            $table->boolean('is_auto')->default(false)->after('attachments');
        });

        Schema::create('mail_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('subject', 200)->nullable();
            $table->text('body');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        $defaults = [
            ['Sapaan awal', null, "Halo {nama},\n\nTerima kasih telah menghubungi {site}. Kami sedang memeriksa kendala Anda dan akan memberi kabar secepatnya."],
            ['Minta detail kendala', null, "Halo {nama},\n\nAgar kami bisa membantu lebih cepat, mohon kirimkan:\n1. Nama domain / layanan yang bermasalah\n2. Penjelasan singkat kendalanya\n3. Tangkapan layar pesan error (kalau ada)\n\nTerima kasih."],
            ['Konfirmasi pembayaran', 'Konfirmasi pembayaran', "Halo {nama},\n\nPembayaran Anda sudah kami terima dan sedang diproses. Layanan akan aktif otomatis dalam beberapa menit; kami akan mengirim detail akses ke email ini."],
            ['Masalah sudah selesai', null, "Halo {nama},\n\nKendala Anda sudah kami tangani. Silakan dicek kembali. Kalau masih ada masalah, balas email ini dan kami bantu lagi."],
            ['Penutup', null, "Terima kasih telah menggunakan layanan {site}. Kalau ada pertanyaan lain, jangan ragu menghubungi kami kembali.\n\nSalam,\n{admin}"],
        ];

        foreach ($defaults as $i => [$title, $subject, $body]) {
            DB::table('mail_templates')->insert([
                'title' => $title, 'subject' => $subject, 'body' => $body,
                'sort' => $i, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_templates');
        Schema::table('mail_messages', function (Blueprint $table) {
            $table->dropColumn('is_auto');
        });
    }
};
