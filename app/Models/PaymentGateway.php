<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentGateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'driver', 'mode', 'server_key', 'client_key', 'callback_token',
        'qris_method_code', 'instructions', 'fee_flat', 'fee_percent', 'currency', 'is_active', 'sort_order',
    ];

    protected $hidden = ['server_key', 'client_key', 'callback_token'];

    protected function casts(): array
    {
        return [
            'server_key'     => 'encrypted',
            'client_key'     => 'encrypted',
            'callback_token' => 'encrypted',
            'fee_flat'       => 'decimal:2',
            'fee_percent'    => 'decimal:2',
            'is_active'      => 'boolean',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isSandbox(): bool
    {
        return $this->mode !== 'production';
    }

    /**
     * Gateway manual tidak memanggil API apapun — pembayaran diverifikasi
     * admin lewat bukti transfer.
     */
    public function isManual(): bool
    {
        return $this->driver === 'manual';
    }

    /**
     * Hitung biaya tambahan gateway untuk nominal tertentu.
     */
    public function calculateFee(float $amount): float
    {
        return round((float) $this->fee_flat + ($amount * (float) $this->fee_percent / 100), 2);
    }

    /**
     * Apakah gateway ini bisa menampilkan kode QRIS langsung di halaman
     * invoice (bukan redirect ke situs Duitku)? Butuh kode metode QRIS
     * yang diisi admin sendiri — lihat migrasi qris_method_code.
     */
    public function supportsEmbeddedQris(): bool
    {
        return $this->driver === 'duitku' && filled($this->qris_method_code);
    }

    public function getDriverLabelAttribute(): string
    {
        return match ($this->driver) {
            'midtrans' => 'Midtrans',
            'xendit'   => 'Xendit',
            'duitku'   => 'Duitku',
            'manual'   => 'Transfer Manual',
            default    => ucfirst($this->driver),
        };
    }
}
