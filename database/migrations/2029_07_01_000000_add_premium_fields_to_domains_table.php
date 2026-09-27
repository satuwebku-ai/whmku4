<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Domain premium (keluarga .id harga tetap MAUPUN ekstensi generik
     * yang harganya baru diketahui setelah admin memberi penawaran)
     * tetap disimpan sebagai baris "domains" biasa -- supaya semua alur
     * yang sudah ada (syarat berkas, invoice, provisioning manual,
     * halaman klien) otomatis berlaku tanpa modul terpisah. Dua kolom
     * ini yang membedakannya dari domain reguler:
     *
     *   - is_premium: dibaca ProvisioningService untuk MEMAKSA jalur
     *     manual (tidak pernah dicoba didaftarkan lewat API registrar
     *     dengan harga normal) -- admin yang menyelesaikan registrasi
     *     sungguhan di panel registrar setelah invoice-nya lunas.
     *   - tld_premium_id: referensi ke baris tld_premiums yang jadi
     *     sumber harga saat item ini dibuat (khusus keluarga .id).
     *     Nullable karena ekstensi generik tidak punya baris tld_premiums
     *     per harga (harganya per-nama, diisi admin langsung ke kolom
     *     price domain ini saat memberi penawaran).
     */
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->boolean('is_premium')->default(false)->after('years');
            $table->foreignId('tld_premium_id')->nullable()->after('tld_id')->constrained('tld_premiums')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tld_premium_id');
            $table->dropColumn('is_premium');
        });
    }
};
