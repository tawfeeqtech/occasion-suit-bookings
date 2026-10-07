<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * AC-003.4: Staff cannot access restricted resources like Users/Staff management.
     */
    public function test_staff_cannot_view_user_management(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->staff($tenant->id)->create();

        $response = $this->actingAs($staff)->get('/admin/users');

        $response->assertStatus(403);
    }

    /**
     * AC-003.4: Staff cannot access restricted shop settings / tenants resource.
     */
    public function test_staff_cannot_view_tenant_settings(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->staff($tenant->id)->create();

        $response = $this->actingAs($staff)->get('/admin/tenants');

        $response->assertStatus(403);
    }

    /**
     * Shop Owner can access both User management and Tenant settings for their store.
     */
    public function test_owner_can_access_user_management_and_settings(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant->id)->create();

        $this->actingAs($owner);

        $usersResponse = $this->get('/admin/users');
        $usersResponse->assertStatus(200);

        $tenantsResponse = $this->get('/admin/tenants');
        $tenantsResponse->assertStatus(200);
    }

    /**
     * Inactive user is barred from accessing the Filament panel.
     */
    public function test_inactive_user_cannot_access_filament_panel(): void
    {
        $tenant = Tenant::factory()->create();
        $inactiveStaff = User::factory()->staff($tenant->id)->inactive()->create();

        $response = $this->actingAs($inactiveStaff)->get('/admin');

        $response->assertStatus(403);
    }
}
