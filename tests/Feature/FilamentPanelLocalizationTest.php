<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentPanelLocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * AC-003.3: Admin panel renders with Arabic locale and RTL direction.
     */
    public function test_admin_login_page_renders_with_rtl_and_arabic(): void
    {
        $response = $this->get('/admin/login');

        $response->assertStatus(200);
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('lang="ar"', false);
    }

    /**
     * Authenticated dashboard view enforces Arabic locale and RTL direction.
     */
    public function test_admin_dashboard_renders_with_rtl_and_arabic_for_owner(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant->id)->create();

        $response = $this->actingAs($owner)->get('/admin');

        $response->assertStatus(200);
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('lang="ar"', false);
        $response->assertSee('سوت رنت', false);
    }
}
