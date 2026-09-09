<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Pembersihan susulan aturan PANDI.
     *
     * Migration 2026_09_01_000000 sudah mematikan WHOIS Privacy untuk
     * keluarga .id, TAPI cuma untuk baris yang ADA SAAT ITU. TLD .id
     * yang masuk BELAKANGAN -- mis. hasil sinkronisasi registrar baru
     * (DNAMA) -- lolos dengan nilai bawaan true, jadi ditawarkan ke
     * klien padahal dilarang.
     *
     * Pencegahan ke depan sudah dipasang di level model (Tld::booted),
     * yang berlaku untuk semua jalur pembuatan. Migration ini khusus
     * membereskan data yang sudah terlanjur ada.
     *
     * Pakai LIKE '%.id' supaya seluruh turunannya ikut: .id, .co.id,
     * .my.id, .web.id, .ac.id, .or.id, .sch.id, .go.id, .biz.id,
     * .net.id, .desa.id, dan apa pun yang berakhiran .id.
     */
    public function up(): void
    {
        DB::table('tlds')
            ->where('whois_privacy_eligible', true)
            ->where(function ($q) {
                $q->where('extension', '.id')->orWhere('extension', 'like', '%.id');
            })
            ->update(['whois_privacy_eligible' => false]);
    }

    public function down(): void
    {
        // Tidak dibalik: mengaktifkan kembali WHOIS Privacy untuk domain
        // .id berarti mengembalikan pelanggaran aturan PANDI.
    }
};
