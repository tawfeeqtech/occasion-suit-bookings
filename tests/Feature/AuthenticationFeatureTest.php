<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * AC-002.1: Web Login & Session Creation
     * Given: A valid user with email owner@shop.com and password secret123.
     * When: Login form is submitted with valid credentials.
     * Then: User is authenticated into session and redirected to dashboard.
     */
    public function test_user_can_login_with_valid_credentials(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->owner($tenant->id)->create([
            'email' => 'owner@shop.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'owner@shop.com',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_authenticated_session_can_load_admin_dashboard_on_a_new_request(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->owner($tenant->id)->create([
            'email' => 'owner@shop.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $this->post('/login', [
            'email' => 'owner@shop.com',
            'password' => 'secret123',
        ])->assertRedirect('/dashboard');

        Auth::forgetGuards();

        $this->get('/admin')->assertOk();
    }

    /**
     * Test login fails with invalid credentials.
     */
    public function test_login_fails_with_invalid_password(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->owner($tenant->id)->create([
            'email' => 'owner@shop.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'owner@shop.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /**
     * AC-002.5: Inactive Users Blocked
     * Given: An inactive user (is_active = false).
     * When: Attempting web login.
     * Then: Access is rejected.
     */
    public function test_inactive_user_cannot_authenticate(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->owner($tenant->id)->inactive()->create([
            'email' => 'inactive_owner@shop.com',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'inactive_owner@shop.com',
            'password' => 'secret123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /**
     * Test user can log out successfully.
     */
    public function test_authenticated_user_can_logout(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->owner($tenant->id)->create();

        $this->actingAs($user);

        $response = $this->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}
