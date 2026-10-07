<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Tenancy\TenantContext;

class AuditService
{
    /**
     * Record an immutable audit log entry.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        string $action,
        string $entityType,
        ?string $entityId,
        array $metadata = [],
        ?User $actor = null,
        ?string $ip = null,
        ?string $tenantId = null
    ): AuditLog {
        $resolvedTenantId = $tenantId ?? TenantContext::getTenantId() ?? $actor?->tenant_id;

        if (! $resolvedTenantId) {
            throw new \InvalidArgumentException('Tenant ID is required to create an audit log entry.');
        }

        $actorType = $actor !== null ? 'user' : 'system';
        $actorId = $actor !== null ? (string) $actor->id : 'scheduler';
        $ipAddress = $ip ?? (app()->runningInConsole() ? null : request()?->ip());

        return AuditLog::create([
            'tenant_id' => $resolvedTenantId,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
            'ip_address' => $ipAddress,
            'created_at' => now(),
        ]);
    }
}
