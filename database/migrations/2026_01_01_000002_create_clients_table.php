<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            // Nomor WhatsApp dipisah dari kolom phone: nomor telepon kantor
            // belum tentu bisa menerima WhatsApp.
            $table->string('whatsapp_number')->nullable();

            // Email transaksional (invoice, tagihan, tiket) SELALU dikirim —
            // itu bagian dari layanan, bukan pilihan. Yang bisa dimatikan
            // klien hanya email promosi, WhatsApp, dan SMS.
            $table->boolean('notify_promo')->default(true);
            $table->boolean('notify_whatsapp')->default(false);
            // SMS dikirim ke kolom `phone` yang sudah ada -- beda dari
            // WhatsApp, SMS tidak butuh nomor terpisah.
            $table->boolean('notify_sms')->default(false);
            $table->string('company')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            // Registrasi domain otomatis butuh data kontak WHOIS lengkap
            // (Namecheap/Liqu.id mewajibkan provinsi & kode pos).
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->default('Indonesia');
            $table->string('password')->nullable(); // untuk nanti login area client (belum aktif di Fase 2)
            // Nullable & unique: diisi hanya untuk akun yang pernah login
            // lewat Google. Klien yang daftar dengan email+password biasa
            // tetap punya nilai null di sini selamanya.
            $table->string('google_id')->nullable()->unique();
            $table->string('avatar')->nullable();
            // Kode reset dikirim lewat email dan disimpan sebagai hash —
            // sama seperti OTP, supaya kode mentah tidak pernah tersimpan.
            $table->string('reset_code_hash')->nullable();
            $table->timestamp('reset_code_expires_at')->nullable();
            $table->unsignedTinyInteger('reset_attempts')->default(0);
            $table->timestamp('email_verified_at')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->decimal('balance', 14, 2)->default(0);
            $table->text('internal_notes')->nullable();
            // 2FA lewat OTP email -- tidak butuh aplikasi authenticator.
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('otp_code_hash')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->unsignedTinyInteger('otp_attempts')->default(0);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
