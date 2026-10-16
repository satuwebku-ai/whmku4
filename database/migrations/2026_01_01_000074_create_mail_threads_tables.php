<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Setiap tabel dibuat hanya bila belum ada, supaya migrasi ini aman dijalankan
     * di database yang sudah punya sebagian tabelnya (mis. hasil restore backup
     * dari masa sebelum migrasi ini dilebur). Kolom yang hilang di tabel yang
     * sudah ada ditangani App\Support\SchemaRepair.
     */
    public function up(): void
    {
        // Catatan setiap email masuk yang sudah diproses: mencegah email yang
        // sama masuk dua kali dan memudahkan penelusuran kalau ada yang hilang.
        if (! Schema::hasTable('inbound_emails')) {
            Schema::create('inbound_emails', function (Blueprint $table) {
                $table->id();
                $table->string('dedupe_key', 64)->unique();
                $table->string('message_id')->nullable();
                $table->string('from_email')->nullable();
                $table->string('subject')->nullable();
                $table->string('result', 120);          // ticket:12 | chat:5 | ignored:... | error:...
                $table->timestamps();
            });
        }

        // Inbox Email: satu thread = satu rangkaian surat dengan satu lawan
        // bicara (contact_email). Terpisah dari Live Chat dan tiket.
        if (! Schema::hasTable('mail_threads')) {
            Schema::create('mail_threads', function (Blueprint $table) {
                $table->id();
                $table->string('subject');
                $table->string('contact_email')->index();
                $table->string('contact_name')->nullable();
                $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
                // Pengunjung yang memilih jalur "Email" di widget chat: pesannya masuk
                // ke Inbox Email. Kolom ini menghubungkan thread dengan percakapan
                // widget-nya, supaya balasan staf dari Inbox Email juga muncul di
                // widget pengunjung.
                $table->foreignId('chat_conversation_id')->nullable()
                    ->constrained('chat_conversations')->nullOnDelete();
                $table->string('status', 20)->default('open')->index();   // open | closed
                $table->unsignedInteger('unread_count')->default(0);
                $table->timestamp('last_message_at')->nullable()->index();
                // Kapan sistem mengirim email "masih perlu bantuan?". Kosong = belum.
                $table->timestamp('idle_prompted_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mail_messages')) {
            Schema::create('mail_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mail_thread_id')->constrained()->cascadeOnDelete();
                $table->string('direction', 3);                            // in | out
                $table->string('from_email');
                $table->string('from_name')->nullable();
                $table->string('to_email');
                $table->string('subject')->nullable();
                $table->longText('body');
                $table->string('message_id')->nullable()->index();         // Message-ID tanpa <>
                $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
                $table->json('attachments')->nullable();                   // [{path,name,mime,size}]
                $table->boolean('is_auto')->default(false);                // balasan otomatis (bot/AI)
                $table->timestamps();
            });
        }

        // Template bawaan hanya diisi saat tabelnya baru dibuat (tidak menimpa template admin).
        $seedTemplates = ! Schema::hasTable('mail_templates');

        if ($seedTemplates) {
            Schema::create('mail_templates', function (Blueprint $table) {
                $table->id();
                $table->string('title', 120);
                $table->string('category', 60)->nullable();
                $table->string('subject', 200)->nullable();
                $table->text('body');
                $table->boolean('is_active')->default(true);
                // Dipakai sebagai bahan pengetahuan bot AI di Live Chat.
                $table->boolean('use_for_ai')->default(false);
                $table->unsignedInteger('sort')->default(0);
                $table->timestamps();
            });
        }

        if (! $seedTemplates) {
            return;
        }

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
        Schema::dropIfExists('mail_messages');
        Schema::dropIfExists('mail_threads');
        Schema::dropIfExists('inbound_emails');
    }
};
