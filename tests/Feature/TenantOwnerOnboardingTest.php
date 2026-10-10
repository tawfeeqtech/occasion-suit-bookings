<?php

namespace Tests\Feature;

use App\Filament\Resources\Tenants\Pages\CreateTenant;
use App\Filament\Resources\Tenants\Pages\EditTenant;
use App\Filament\Resources\Tenants\Pages\ListTenants;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Filament\Resources\Events\RecordCreated;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class TenantOwnerOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_shop_and_owner_are_created_atomically(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $this->actingAs($admin);
        $eventData = null;

        Event::listen(RecordCreated::class, function (Tenant $record, array $data) use (&$eventData): void {
            $eventData = $data;
        });

        Livewire::test(CreateTenant::class)
            ->fillForm([
                'name' => 'Al Noor Suits',
                'slug' => 'al-noor-suits',
                'is_active' => true,
                'settings' => ['buffer_hours' => 48, 'currency' => 'ILS'],
                'owner' => [
                    'name' => 'Shop Owner',
                    'email' => 'owner@alnoor.test',
                    'password' => 'initial-password-123',
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tenant = Tenant::query()->where('slug', 'al-noor-suits')->firstOrFail();
        $owner = User::withoutGlobalScopes()->where('email', 'owner@alnoor.test')->firstOrFail();

        $this->assertSame('owner', $owner->role);
        $this->assertSame($tenant->id, $owner->tenant_id);
        $this->assertDatabaseCount('tenants', 1);
        $this->assertDatabaseCount('users', 2);
        $this->assertIsArray($eventData);
        $this->assertArrayNotHasKey('owner', $eventData);
        $this->assertStringNotContainsString('initial-password-123', json_encode($eventData));
    }

    public function test_owner_initial_password_is_stored_as_a_hash(): void
    {
        $this->actingAs(User::factory()->systemAdmin()->create());

        Livewire::test(CreateTenant::class)
            ->fillForm([
                'name' => 'Password Shop',
                'slug' => 'password-shop',
                'settings' => ['buffer_hours' => 48, 'currency' => 'ILS'],
                'owner' => [
                    'name' => 'Password Owner',
                    'email' => 'password-owner@test.test',
                    'password' => 'start-password-123',
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $owner = User::withoutGlobalScopes()->where('email', 'password-owner@test.test')->firstOrFail();

        $this->assertNotSame('start-password-123', $owner->getRawOriginal('password'));
        $this->assertTrue(Hash::check('start-password-123', $owner->getRawOriginal('password')));
    }

    public function test_owner_credentials_are_required_only_for_shop_creation(): void
    {
        $this->actingAs(User::factory()->systemAdmin()->create());

        Livewire::test(CreateTenant::class)
            ->fillForm([
                'name' => 'Incomplete Shop',
                'slug' => 'incomplete-shop',
                'settings' => ['buffer_hours' => 48, 'currency' => 'ILS'],
            ])
            ->call('create')
            ->assertHasFormErrors();

        $this->assertDatabaseCount('tenants', 0);
    }

    public function test_owner_credentials_are_not_part_of_tenant_editing(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant->id)->create();
        $this->actingAs(User::factory()->systemAdmin()->create());

        Livewire::test(EditTenant::class, ['record' => $tenant->getRouteKey()])
            ->fillForm([
                'name' => 'Edited Shop',
                'slug' => $tenant->slug,
                'is_active' => true,
                'settings' => $tenant->settings,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($owner->id, User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('role', 'owner')->value('id'));
        $this->assertSame('Edited Shop', $tenant->fresh()->name);
    }

    public function test_shop_creation_is_system_admin_only(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant->id)->create();

        $this->actingAs($owner)
            ->get('/admin/tenants/create')
            ->assertForbidden();

        $this->assertDatabaseCount('tenants', 1);
    }

    public function test_tenant_creation_rolls_back_when_owner_email_is_already_used(): void
    {
        User::factory()->create(['email' => 'duplicate-owner@test.test']);
        $this->actingAs(User::factory()->systemAdmin()->create());

        Livewire::test(CreateTenant::class)
            ->fillForm([
                'name' => 'Duplicate Email Shop',
                'slug' => 'duplicate-email-shop',
                'settings' => ['buffer_hours' => 48, 'currency' => 'ILS'],
                'owner' => [
                    'name' => 'Duplicate Owner',
                    'email' => 'duplicate-owner@test.test',
                    'password' => 'start-password-123',
                ],
            ])
            ->call('create')
            ->assertHasFormErrors();

        $this->assertDatabaseMissing('tenants', ['slug' => 'duplicate-email-shop']);
        $this->assertDatabaseCount('tenants', 0);
    }

    public function test_tenant_creation_rolls_back_if_owner_persistence_fails(): void
    {
        $this->actingAs(User::factory()->systemAdmin()->create());

        User::creating(function (User $user): void {
            if ($user->email === 'owner-insert-failure@test.test') {
                throw new \RuntimeException('Simulated owner persistence failure.');
            }
        });

        try {
            Livewire::test(CreateTenant::class)
                ->fillForm([
                    'name' => 'Rollback Shop',
                    'slug' => 'rollback-shop',
                    'settings' => ['buffer_hours' => 48, 'currency' => 'ILS'],
                    'owner' => [
                        'name' => 'Rollback Owner',
                        'email' => 'owner-insert-failure@test.test',
                        'password' => 'rollback-password-123',
                    ],
                ])
                ->call('create');

            $this->fail('Owner persistence should throw and roll back tenant creation.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated owner persistence failure.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('tenants', ['slug' => 'rollback-shop']);
        $this->assertDatabaseMissing('users', ['email' => 'owner-insert-failure@test.test']);
    }

    public function test_owner_login_uses_assigned_tenant_context(): void
    {
        $this->actingAs(User::factory()->systemAdmin()->create());
        Livewire::test(CreateTenant::class)
            ->fillForm([
                'name' => 'Tenant One',
                'slug' => 'tenant-one',
                'settings' => ['buffer_hours' => 48, 'currency' => 'ILS'],
                'owner' => [
                    'name' => 'Tenant One Owner',
                    'email' => 'tenant-one-owner@test.test',
                    'password' => 'tenant-password-123',
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Tenant::factory()->create(['name' => 'Tenant Two', 'slug' => 'tenant-two']);
        Auth::forgetGuards();

        $this->post('/login', [
            'email' => 'tenant-one-owner@test.test',
            'password' => 'tenant-password-123',
        ])->assertRedirect('/dashboard');

        $owner = User::withoutGlobalScopes()->where('email', 'tenant-one-owner@test.test')->firstOrFail();
        $this->get('/admin/tenants')->assertOk();

        $this->assertSame($owner->tenant_id, TenantContext::getTenantId());
        Livewire::test(ListTenants::class)
            ->assertCanSeeTableRecords([Tenant::findOrFail($owner->tenant_id)])
            ->assertCanNotSeeTableRecords([Tenant::where('slug', 'tenant-two')->firstOrFail()]);
    }

    public function test_slug_does_not_determine_user_tenant(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'old-shop-name']);
        $owner = User::factory()->owner($tenant->id)->create();
        $staff = User::factory()->staff($tenant->id)->create();
        $item = Item::factory()->create(['tenant_id' => $tenant->id]);

        $tenant->update(['slug' => 'new-shop-name']);
        $this->actingAs($owner);

        $this->assertSame($tenant->id, $owner->fresh()->tenant_id);
        $this->assertSame($tenant->id, $staff->fresh()->tenant_id);
        $this->assertTrue(Item::query()->whereKey($item->id)->exists());
    }

    public function test_only_one_active_owner_per_shop_is_enforced_by_postgresql(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->owner($tenant->id)->create();

        try {
            DB::transaction(fn () => User::factory()->owner($tenant->id)->create());
            $this->fail('A second active owner should violate the partial unique index.');
        } catch (QueryException) {
            $this->assertSame(
                1,
                User::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('role', 'owner')
                    ->where('is_active', true)
                    ->count()
            );
        }
    }

    public function test_owner_account_requires_a_tenant_in_postgresql(): void
    {
        try {
            DB::transaction(fn () => User::factory()->owner()->create([
                'email' => 'unassigned-owner@test.test',
            ]));
            $this->fail('An owner account without a tenant should violate the database constraint.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('users', ['email' => 'unassigned-owner@test.test']);
        }
    }

    public function test_system_admin_can_replace_deactivated_owner_and_old_record_is_retained(): void
    {
        $tenant = Tenant::factory()->create();
        $formerOwner = User::factory()->owner($tenant->id)->inactive()->create();
        $admin = User::factory()->systemAdmin()->create();
        $this->actingAs($admin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Replacement Owner',
                'email' => 'replacement-owner@test.test',
                'password' => 'replacement-password-123',
                'role' => 'owner',
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $replacement = User::withoutGlobalScopes()->where('email', 'replacement-owner@test.test')->firstOrFail();

        $this->assertSame('owner', $replacement->role);
        $this->assertSame($tenant->id, $replacement->tenant_id);
        $this->assertFalse($formerOwner->fresh()->is_active);
        $this->assertDatabaseHas('users', ['id' => $formerOwner->id]);
    }
}
