<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainContact extends Model
{
    protected $fillable = [
        'domain_id', 'type', 'first_name', 'last_name', 'organization',
        'email', 'phone', 'address', 'city', 'state', 'postal_code', 'country',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
