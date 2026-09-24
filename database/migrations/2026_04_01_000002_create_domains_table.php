<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('registrar_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tld_id')->nullable()->constrained()->nullOnDelete();
            $table->string('domain_name'); // domain lengkap, mis. "contoh.com"
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedTinyInteger('years')->default(1);
            $table->enum('status', ['pending', 'active', 'expired', 'cancelled'])->default('pending');
            $table->date('register_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->boolean('whois_privacy')->default(false);
            // Sebelumnya klien bisa mengaktifkan ID Protection sendiri
            // secara GRATIS lewat tombol di halaman domain — padahal tiap
            // aktivasi memotong saldo deposit reseller di registrar.
            // Dengan kolom ini, aktivasi ditahan sampai invoice-nya dibayar.
            $table->foreignId('privacy_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            // ID Protection punya masa berlaku SENDIRI (1 tahun sejak
            // aktif), TERPISAH dari masa aktif domain — supaya klien
            // selalu dapat 12 bulan penuh berapa pun sisa umur domainnya.
            $table->date('privacy_expires_at')->nullable();
            $table->json('nameservers')->nullable();
            // Invoice perpanjangan yang sedang menunggu dibayar untuk
            // domain ini -- sama polanya seperti hosting_accounts.
            $table->foreignId('renewal_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('provision_status')->default('manual'); // manual, registered, failed
            $table->timestamp('provisioning_started_at')->nullable();
            $table->timestamp('provisioning_finished_at')->nullable();
            $table->unsignedInteger('provisioning_attempts')->default(0);
            $table->uuid('provisioning_key')->nullable()->unique();
            // Membedakan "domain baru didaftarkan" dari "domain dipindah
            // dari registrar lain" — ProvisioningService perlu tahu API
            // mana yang dipanggil (registerDomain vs transferDomain).
            $table->boolean('is_transfer')->default(false);
            // Kode EPP/Auth dari registrar lama — dienkripsi karena setara
            // password sekali pakai untuk memindahkan domain.
            $table->text('transfer_auth_code')->nullable();
            $table->text('provision_message')->nullable();
            // Beberapa TLD (.asia, .ca, .coop, .es, .jobs, .nl, .pro, .ru,
            // .us) mewajibkan data kelayakan (eligibility) tambahan dari
            // registry aslinya sebelum bisa didaftarkan.
            $table->string('eligibility_criteria')->nullable();
            $table->string('eligibility_extra')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
