<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostingPackage extends Model
{
    protected $fillable = [
        'product_id', 'name', 'disk_quota_mb', 'bandwidth_mb', 'email_accounts',
        'databases', 'ftp_accounts', 'addon_domains', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
