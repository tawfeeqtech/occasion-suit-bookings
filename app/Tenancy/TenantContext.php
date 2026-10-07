<?php

namespace App\Tenancy;

class TenantContext
{
    /**
     * Explicit tenant ID override (for tests or background tasks).
     */
    protected static ?string $tenantId = null;

    /**
     * Get the active tenant ID.
     */
    public static function getTenantId(): ?string
    {
        if (static::$tenantId !== null) {
            return static::$tenantId;
        }

        if (auth()->check()) {
            $user = auth()->user();

            return $user->tenant_id ?? null;
        }

        return null;
    }

    /**
     * Set the current tenant ID explicitly.
     */
    public static function setTenantId(?string $tenantId): void
    {
        static::$tenantId = $tenantId;
    }

    /**
     * Reset the active tenant override.
     */
    public static function clear(): void
    {
        static::$tenantId = null;
    }
}
