<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TldPremium extends Model
{
    /**
     * Ekstensi keluarga .id -- PANDI menetapkan tingkat harga premium
     * TETAP berdasarkan jumlah karakter, jadi DNAMA mengirimnya sebagai
     * daftar harga siap pakai (bukan per-nama seperti TLD generik di
     * bawah). Dipakai bersama oleh TldController (sinkron & kelola
     * admin) dan PremiumDomainController (tampilan publik) supaya
     * daftarnya tidak dobel dan gampang beda kalau salah satu diubah.
     */
    public const ID_FAMILY = [
        '.id', '.co.id', '.my.id', '.ac.id', '.sch.id',
        '.or.id', '.web.id', '.biz.id', '.ponpes.id',
    ];

    /**
     * Ekstensi generik yang DNAMA izinkan dicek/dipesan sebagai domain
     * premium, tapi harganya PER-NAMA -- tidak ada daftar harga tetap
     * seperti keluarga .id di atas. Alur pemesanannya manual (bukan
     * lewat Reseller API), jadi baris ini di database cuma referensi
     * daftar dukungan (is_generic = true), tanpa cost_ / sell_.
     */
    public const GENERIC_EXTENSIONS = [
        '.com', '.org', '.net', '.asia', '.biz', '.info', '.xyz', '.co',
        '.tv', '.name', '.mobi', '.cc', '.education', '.institute',
        '.foundation', '.store', '.travel', '.com.my',
    ];

    protected $fillable = [
        'registrar_id',
        'extension',
        'is_premium',
        'max_premium_character',
        'label',
        'is_generic',
        'cost_register',
        'cost_renew',
        'cost_transfer',
        'cost_currency',
        'cost_synced_at',
        'sell_register_price',
        'sell_renew_price',
        'sell_transfer_price',
        'is_active',
    ];

    protected $casts = [
        'is_premium' => 'boolean',
        'is_generic' => 'boolean',
        'is_active' => 'boolean',
        'cost_synced_at' => 'datetime',
        'cost_register' => 'decimal:2',
        'cost_renew' => 'decimal:2',
        'cost_transfer' => 'decimal:2',
        'sell_register_price' => 'decimal:2',
        'sell_renew_price' => 'decimal:2',
        'sell_transfer_price' => 'decimal:2',
    ];

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(Registrar::class);
    }
}