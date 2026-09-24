<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketDepartment extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'is_default', 'sort_order'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }
}
