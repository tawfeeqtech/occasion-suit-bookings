<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * AC-001.2: Cross-Tenant Data Leakage Prevention
     * Given: Two tenants exist: Tenant_A with 5 users and Tenant_B with 3 users.
     * When: A user belonging to Tenant_A executes User::all().
     * Then: Exactly Tenant_A's users are returned, and none of Tenant_B's user IDs exist in the collection.
     */
    public function test_cannot_retrieve_foreign_tenant_records(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $ownerA = User::factory()->owner($tenantA->id)->create();
        $staffA = User::factory()->count(4)->staff($tenantA->id)->create();

        $ownerB = User::factory()->owner($tenantB->id)->create();
        $staffB = User::factory()->count(2)->staff($tenantB->id)->create();

        // Authenticated as Tenant A owner
        $this->actingAs($ownerA);

        $results = User::all();

        // Total users belonging to tenant A is 1 (ownerA) + 4 (staffA) = 5
        $this->assertCount(5, $results);

        $tenantBUserIds = $staffB->pluck('id')->push($ownerB->id)->all();
        foreach ($results as $user) {
            $this->assertEquals($tenantA->id, $user->tenant_id);
            $this->assertNotContains($user->id, $tenantBUserIds);
        }
    }

    /**
     * AC-001.4: Database Level Row Scoping Enforcement
     * Given: A direct Eloquent query builder execution User::where('is_active', true)->toSql().
     * When: Inspected via toSql().
     * Then: The generated SQL contains "tenant_id" = ?.
     */
    public function test_query_sql_always_contains_tenant_where_clause(): void
    {
        $tenantA = Tenant::factory()->create();
        $ownerA = User::factory()->owner($tenantA->id)->create();

        $this->actingAs($ownerA);

        $query = User::where('is_active', true);
        $sql = $query->toSql();

        $this->assertStringContainsString('"users"."tenant_id" = ?', $sql);
    }

    /**
     * Test global scope can be bypassed explicitly with withoutGlobalScopes when required (e.g. system admin).
     */
    public function test_system_admin_or_unscoped_query_can_access_all(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        User::factory()->owner($tenantA->id)->create();
        User::factory()->owner($tenantB->id)->create();

        $admin = User::factory()->systemAdmin()->create();
        $this->actingAs($admin); // admin has tenant_id = null

        // With tenant_id null, no TenantScope filter is applied
        $allUsers = User::all();
        $this->assertGreaterThanOrEqual(3, $allUsers->count());
    }

    /**
     * REQ-001-05: Tenant settings JSONB isolation
     */
    public function test_tenant_settings_jsonb_isolation(): void
    {
        $tenantA = Tenant::factory()->create([
            'settings' => ['buffer_hours' => 24, 'currency' => 'USD'],
        ]);

        $tenantB = Tenant::factory()->create([
            'settings' => ['buffer_hours' => 72, 'currency' => 'JOD'],
        ]);

        $this->assertEquals(24, $tenantA->fresh()->settings['buffer_hours']);
        $this->assertEquals('USD', $tenantA->fresh()->settings['currency']);

        $this->assertEquals(72, $tenantB->fresh()->settings['buffer_hours']);
        $this->assertEquals('JOD', $tenantB->fresh()->settings['currency']);
    }
}
