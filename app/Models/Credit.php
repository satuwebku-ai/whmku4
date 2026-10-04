<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Credit extends Model
{
    /**
     * Jenis mutasi buku besar. Kolom `type` berupa string (bukan enum
     * database) supaya jenis baru cukup ditambah di sini tanpa migrasi.
     */
    public const TYPES = [
        'topup' => 'Isi Ulang',
        'payment' => 'Bayar Invoice',
        'refund' => 'Refund',
        'admin_adjustment' => 'Penyesuaian Admin',
        'usage_charge' => 'Pemakaian Layanan',
        'topup_reversal' => 'Pembatalan Isi Ulang',
    ];

    protected $fillable = [
        'client_id', 'amount', 'type', 'description', 'invoice_id', 'admin_id', 'balance_after',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }
}
