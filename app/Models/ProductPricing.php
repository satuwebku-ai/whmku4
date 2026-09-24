<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPricing extends Model
{
    protected $fillable = ['product_id', 'client_group_id', 'billing_cycle', 'price', 'setup_fee'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'setup_fee' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function clientGroup(): BelongsTo
    {
        return $this->belongsTo(ClientGroup::class);
    }
}
