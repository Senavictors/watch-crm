<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    protected function audit(
        string $action,
        ?string $description = null,
        mixed $auditable = null,
        array $metadata = []
    ): void {
        AuditLogger::log($action, $description, $auditable, $metadata);
    }
}
