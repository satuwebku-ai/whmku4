<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menyimpan harga domain PREMIUM -- beda konsep dari tabel `tlds`
     * biasa karena satu ekstensi (mis. ".id") bisa punya BEBERAPA baris
     * sekaligus: harga reguler + beberapa tingkat premium berdasarkan
     * jumlah karakter (2/3/4 karakter, dst -- lihat max_premium_character).
     * DNAMA mengirim baris-baris ini terpisah lewat /customer-tld-pricings
     * meski nama ekstensinya sama persis (lihat catatan di DnamaService).
     *
     * Dipisah dari tabel `tlds` (yang cuma menampung SATU harga flat per
     * ekstensi per registrar) supaya sinkronisasi harga modal biasa tidak
     * perlu tahu-menahu soal struktur berjenjang ini.
     *
     * cost_* = harga MODAL, ditarik otomatis dari DNAMA (read-only di UI,
     * ditimpa tiap sinkronisasi). sell_* = harga JUAL milik kita sendiri,
     * diisi manual oleh admin dan TIDAK PERNAH ditimpa oleh sinkronisasi.
     */
    public function up(): void
    {
        Schema::create('tld_premiums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registrar_id')->nullable()->constrained()->nullOnDelete();

            $table->string('extension'); // ".id", ".co.id", dst.
            $table->boolean('is_premium')->default(false);
            $table->unsignedTinyInteger('max_premium_character')->nullable();
            $table->string('label'); // "* .id (2 karakter) Premium" -- siap tampil, tidak perlu dihitung ulang di view.

            // TRUE untuk ekstensi generik (.com, .org, dst) yang harga
            // premiumnya PER-NAMA (bukan daftar tetap) -- baris ini cuma
            // referensi daftar dukungan, tidak punya cost_*/sell_*.
            $table->boolean('is_generic')->default(false);

            $table->decimal('cost_register', 14, 2)->nullable();
            $table->decimal('cost_renew', 14, 2)->nullable();
            $table->decimal('cost_transfer', 14, 2)->nullable();
            $table->string('cost_currency', 3)->default('IDR');
            $table->timestamp('cost_synced_at')->nullable();

            $table->decimal('sell_register_price', 14, 2)->nullable();
            $table->decimal('sell_renew_price', 14, 2)->nullable();
            $table->decimal('sell_transfer_price', 14, 2)->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Satu baris per kombinasi registrar + ekstensi + tingkat
            // premium -- max_premium_character ikut dibedakan lewat index
            // biasa (bukan unique constraint) karena sebagian driver DB
            // memperlakukan NULL secara longgar di unique index gabungan;
            // pencocokan baris yang aman dilakukan di level aplikasi.
            $table->index(['registrar_id', 'extension']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tld_premiums');
    }
};
