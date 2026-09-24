<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateAuditLog extends Model
{
    protected $fillable = [
        'admin_id', 'affiliate_id', 'action', 'old_value', 'new_value',
        'reason', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['old_value' => 'array', 'new_value' => 'array'];
    }

    public function admin(): BelongsTo { return $this->belongsTo(Admin::class); }
    public function affiliate(): BelongsTo { return $this->belongsTo(Affiliate::class); }
}