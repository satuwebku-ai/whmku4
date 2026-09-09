<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostingAccountAddon extends Model
{
    protected $fillable = ['hosting_account_id', 'addon_id', 'name', 'price', 'status', 'invoice_id'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    public function hostingAccount(): BelongsTo
    {
        return $this->belongsTo(HostingAccount::class);
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
