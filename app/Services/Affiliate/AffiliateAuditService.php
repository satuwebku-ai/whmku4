<?php

namespace App\Services\Affiliate;

use App\Models\Admin;
use App\Models\Affiliate;
use App\Models\AffiliateAuditLog;
use Illuminate\Http\Request;

class AffiliateAuditService
{
    public function record(
        string $action,
        ?Affiliate $affiliate = null,
        ?Admin $admin = null,
        ?array $oldValue = null,
        ?array $newValue = null,
        ?string $reason = null,
        ?Request $request = null,
    ): AffiliateAuditLog {
        $request ??= app()->bound('request') ? request() : null;

        return AffiliateAuditLog::create([
            'admin_id' => $admin?->id,
            'affiliate_id' => $affiliate?->id,
            'action' => $action,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'reason' => $reason,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}