<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialAndAuditAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * REQ-007-07 & REQ-008-05: Staff are strictly barred from financial reports and audit logs.
     */
    public function test_staff_blocked_from_financial_reports_and_audit_logs(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->staff($tenant->id)->create();

        $this->actingAs($staff);

        $responseReports = $this->get('/admin/financial-reports');
        $responseReports->assertStatus(403);

        $responseAudit = $this->get('/admin/audit-logs');
        $responseAudit->assertStatus(403);
    }

    /**
     * Owner can access both financial reports and audit logs.
     */
    public function test_owner_can_access_financial_reports_and_audit_logs(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant->id)->create();

        $this->actingAs($owner);

        $responseReports = $this->get('/admin/financial-reports');
        $responseReports->assertStatus(200);

        $responseAudit = $this->get('/admin/audit-logs');
        $responseAudit->assertStatus(200);
    }
}
