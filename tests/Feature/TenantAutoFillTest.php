<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantAutoFillTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * AC-001.1: Automated Model Creation Tenant Binding
     * Given: An authenticated request with active tenant tenant_A.
     * When: An Eloquent model is created without specifying tenant_id.
     * Then: tenant_id is automatically set to tenant_A->id before persisting.
     */
    public function test_model_automatically_sets_tenant_id_on_create(): void
    {
        $tenantA = Tenant::factory()->create();
        $owner = User::factory()->owner($tenantA->id)->create();

        $this->actingAs($owner);

        // Create a model without passing tenant_id explicitly
        $staffUser = User::create([
            'name' => 'Assigned Staff',
            'email' => 'assigned_staff@test.com',
            'password' => 'secret123',
            'role' => 'staff',
        ]);

        $this->assertNotNull($staffUser->tenant_id);
        $this->assertEquals($tenantA->id, $staffUser->tenant_id);
        $this->assertDatabaseHas('users', [
            'id' => $staffUser->id,
            'tenant_id' => $tenantA->id,
            'email' => 'assigned_staff@test.com',
        ]);
    }

    /**
     * Test model automatically sets tenant_id when TenantContext is set explicitly.
     */
    public function test_model_automatically_sets_tenant_id_from_context(): void
    {
        $tenantB = Tenant::factory()->create();
        TenantContext::setTenantId($tenantB->id);

        $user = User::create([
            'name' => 'Context Staff',
            'email' => 'context_staff@test.com',
            'password' => 'secret123',
            'role' => 'staff',
        ]);

        $this->assertEquals($tenantB->id, $user->tenant_id);
    }

    /**
     * Test model preserves explicitly passed tenant_id.
     */
    public function test_model_preserves_explicitly_passed_tenant_id(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $ownerA = User::factory()->owner($tenantA->id)->create();
        $this->actingAs($ownerA);

        $user = User::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Explicit Tenant User',
            'email' => 'explicit@test.com',
            'password' => 'secret123',
            'role' => 'staff',
        ]);

        $this->assertEquals($tenantB->id, $user->tenant_id);
    }
}
