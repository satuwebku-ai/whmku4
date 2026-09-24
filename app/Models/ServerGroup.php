<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServerGroup extends Model
{
    protected $fillable = ['name', 'slug', 'description'];

    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }
}
