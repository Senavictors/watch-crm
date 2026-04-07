<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public static function log(
        string $action,
        ?string $description = null,
        mixed $auditable = null,
        array $metadata = []
    ): void {
        $attributes = [
            'user_id' => Auth::id(),
            'action' => $action,
            'description' => $description,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'metadata' => $metadata ?: null,
        ];

        if ($auditable instanceof Model) {
            $attributes['auditable_type'] = $auditable::class;
            $attributes['auditable_id'] = $auditable->getKey();
        }

        AuditLog::create($attributes);
    }
}
