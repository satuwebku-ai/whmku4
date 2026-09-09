<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientBalanceLog extends Model
{
    protected $fillable = [
        'client_id', 'amount', 'type', 'description', 'invoice_id', 'admin_id', 'balance_after',
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
        return match ($this->type) {
            'topup' => 'Isi Ulang',
            'payment' => 'Bayar Invoice',
            'refund' => 'Refund',
            'admin_adjustment' => 'Penyesuaian Admin',
            default => ucfirst($this->type),
        };
    }
}
