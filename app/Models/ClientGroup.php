<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientGroup extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'discount_percentage', 'is_default'];

    protected function casts(): array
    {
        return [
            'discount_percentage' => 'decimal:2',
            'is_default' => 'boolean',
        ];
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }
}
