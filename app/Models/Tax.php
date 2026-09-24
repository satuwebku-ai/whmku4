<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    protected $fillable = ['name', 'rate_percentage', 'country', 'is_active'];

    protected function casts(): array
    {
        return [
            'rate_percentage' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
