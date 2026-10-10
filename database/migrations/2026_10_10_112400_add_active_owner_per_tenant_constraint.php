<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Enforce no more than one active owner for each tenant.
     */
    public function up(): void
    {
        $ownersWithoutTenant = DB::table('users')
            ->where('role', 'owner')
            ->whereNull('tenant_id')
            ->count();

        if ($ownersWithoutTenant > 0) {
            throw new RuntimeException(
                'Cannot enforce tenant assignment for owners: '.$ownersWithoutTenant.' owner account(s) have no tenant. Resolve manually before retrying.'
            );
        }

        $duplicates = DB::table('users')
            ->select('tenant_id')
            ->where('role', 'owner')
            ->where('is_active', true)
            ->whereNotNull('tenant_id')
            ->groupBy('tenant_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot create active-owner constraint: '.$duplicates->count().' tenant(s) have duplicate active owners. Resolve manually before retrying.'
            );
        }

        DB::statement(
            "ALTER TABLE users
            ADD CONSTRAINT users_owner_requires_tenant
            CHECK (role <> 'owner' OR tenant_id IS NOT NULL)"
        );

        DB::statement(
            "CREATE UNIQUE INDEX users_one_active_owner_per_tenant_unique
            ON users (tenant_id)
            WHERE role = 'owner' AND is_active = true AND tenant_id IS NOT NULL"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_one_active_owner_per_tenant_unique');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_owner_requires_tenant');
    }
};
