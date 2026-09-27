<?php

namespace App\Services\Kpi;

use App\Models\Kpi\KpiAuditLog;

class KpiAuditService
{
    public static function log(string $action, string $entityType, int $entityId, ?array $old = null, ?array $new = null): KpiAuditLog
    {
        return KpiAuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
