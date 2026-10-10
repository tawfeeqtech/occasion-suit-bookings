<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TenantStaffAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_owner_staff_inherits_owner_tenant_even_if_a_foreign_tenant_is_submitted(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $owner = User::factory()->owner($tenantA->id)->create();

        $this->actingAs($owner);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'New Staff',
                'email' => 'new-staff@test.test',
                'password' => 'staff-password-123',
                'role' => 'staff',
                'tenant_id' => $tenantB->id,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $createdUser = User::withoutGlobalScopes()->where('email', 'new-staff@test.test')->firstOrFail();
        $this->assertSame('staff', $createdUser->role);
        $this->assertSame($tenantA->id, $createdUser->tenant_id);
    }

    public function test_owner_cannot_promote_staff_to_owner(): void
    {
        $tenantA = Tenant::factory()->create();
        $owner = User::factory()->owner($tenantA->id)->create();
        $staff = User::factory()->staff($tenantA->id)->create();

        $this->actingAs($owner);

        Livewire::test(EditUser::class, ['record' => $staff->getRouteKey()])
            ->fillForm([
                'name' => $staff->name,
                'email' => $staff->email,
                'password' => null,
                'role' => 'owner',
                'tenant_id' => $tenantA->id,
                'is_active' => true,
            ])
            ->call('save')
            ->assertHasFormErrors();

        $updatedUser = $staff->fresh();
        $this->assertSame('staff', $updatedUser->role);
        $this->assertSame($tenantA->id, $updatedUser->tenant_id);
    }

    public function test_owner_cannot_create_an_owner_account(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant->id)->create();

        $this->actingAs($owner);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Attempted Owner',
                'email' => 'attempted-owner@test.test',
                'password' => 'attempted-password-123',
                'role' => 'owner',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors();

        $this->assertDatabaseMissing('users', ['email' => 'attempted-owner@test.test']);
    }

    public function test_owner_cannot_assign_staff_to_another_tenant_on_edit(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $owner = User::factory()->owner($tenantA->id)->create();
        $staff = User::factory()->staff($tenantA->id)->create();

        $this->actingAs($owner);

        Livewire::test(EditUser::class, ['record' => $staff->getRouteKey()])
            ->fillForm([
                'name' => $staff->name,
                'email' => $staff->email,
                'password' => null,
                'role' => 'staff',
                'tenant_id' => $tenantB->id,
                'is_active' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($tenantA->id, $staff->fresh()->tenant_id);
    }

    public function test_system_admin_must_select_a_valid_staff_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->systemAdmin()->create();
        $this->actingAs($admin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Unassigned Staff',
                'email' => 'unassigned@test.test',
                'password' => 'staff-password-123',
                'role' => 'staff',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Invalid Tenant Staff',
                'email' => 'invalid-tenant@test.test',
                'password' => 'staff-password-123',
                'role' => 'staff',
                'tenant_id' => '00000000-0000-4000-8000-000000000000',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Assigned Staff',
                'email' => 'assigned@test.test',
                'password' => 'staff-password-123',
                'role' => 'staff',
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $createdUser = User::withoutGlobalScopes()->where('email', 'assigned@test.test')->firstOrFail();
        $this->assertSame($tenant->id, $createdUser->tenant_id);
    }

    public function test_system_admin_cannot_create_another_active_owner_before_deactivating_the_current_owner(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->owner($tenant->id)->create();
        $admin = User::factory()->systemAdmin()->create();
        $this->actingAs($admin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Duplicate Owner',
                'email' => 'duplicate-owner@test.test',
                'password' => 'duplicate-password-123',
                'role' => 'owner',
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors();

        $this->assertDatabaseMissing('users', ['email' => 'duplicate-owner@test.test']);
    }
}
