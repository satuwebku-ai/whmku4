<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hosting_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            // servers baru dibuat belakangan (2026_03_01) -- FK dipasang
            // di 2026_03_01_000000_create_servers_table.php.
            $table->foreignId('server_id')->nullable();
            // products baru dibuat belakangan (2026_10_01) -- FK dipasang
            // di 2026_10_01_000001_create_products_table.php.
            $table->foreignId('product_id')->nullable();
            $table->string('domain');
            $table->string('package'); // nama paket, mis. "Cloud Hosting - Pro"
            $table->string('server')->nullable(); // nama server, akan jadi relasi ke tabel servers di Fase 3
            $table->string('panel')->default('cpanel'); // cpanel, directadmin, plesk — dipakai di Fase 3
            $table->string('username')->nullable(); // username akun di panel hosting
            $table->decimal('price', 12, 2)->default(0);
            $table->enum('billing_cycle', ['monthly', 'quarterly', 'semi_annually', 'annually', 'custom'])->default('monthly');
            // Fondasi generik untuk layanan yang ditagih PER JAM dari saldo
            // (deposit) -- BUKAN lewat invoice bulanan seperti hosting biasa.
            // Modul VM/VPS tinggal mengisi billing_mode='deposit' + hourly_rate.
            $table->enum('billing_mode', ['invoice', 'deposit'])->default('invoice');
            $table->decimal('hourly_rate', 12, 4)->nullable();
            $table->timestamp('last_billed_at')->nullable();
            $table->enum('status', ['pending', 'active', 'suspended', 'terminated'])->default('pending');

            // Pembatalan tidak langsung mematikan layanan — klien mengajukan,
            // admin meninjau dan memprosesnya. Kolom status di atas sengaja
            // tidak disentuh sampai admin menyetujui, supaya layanan tidak
            // berhenti sendiri hanya karena klien salah klik.
            $table->enum('cancellation_status', ['none', 'requested', 'approved', 'declined'])->default('none');
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancellation_requested_at')->nullable();
            $table->text('cancellation_admin_note')->nullable();

            $table->date('next_due_date')->nullable();

            // Invoice perpanjangan yang sedang menunggu dibayar untuk
            // layanan ini -- tanpa ini sistem tidak tahu apakah sudah
            // pernah membuat invoice untuk siklus saat ini, dan bisa
            // membuat invoice duplikat tiap kali perintah terjadwal
            // berjalan. Dikosongkan lagi setelah invoice lunas. invoices
            // dibuat di migration ini juga tapi belakangan (000003) --
            // FK dipasang di sana.
            $table->foreignId('renewal_invoice_id')->nullable();

            // Upgrade menunggu pembayaran -- dua kolom ini dikosongkan lagi
            // begitu invoice-nya lunas dan upgrade benar-benar diterapkan.
            // FK juga dipasang di 2026_10_01_000001_create_products_table.php
            // dan bersamaan dengan renewal_invoice_id di atas.
            $table->foreignId('pending_upgrade_product_id')->nullable();
            $table->foreignId('pending_upgrade_invoice_id')->nullable();

            $table->string('provision_status')->default('manual'); // manual, provisioned, failed
            $table->text('provision_message')->nullable(); // pesan sukses/error terakhir dari API panel
            // Untuk layanan yang provisioning-nya manual (VPS, dedicated
            // server, lisensi software, dst), tidak ada cara klien melihat
            // info aksesnya sama sekali. Kolom bebas ini diisi admin sendiri
            // setelah setup manual di luar sistem (IP, root password, URL
            // panel VPS, dll), lalu ditampilkan langsung ke klien. Dienkripsi
            // karena isinya sering berupa kredensial sensitif.
            $table->text('client_details')->nullable();
            $table->text('internal_notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hosting_accounts');
    }
};
