<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * AC-008.2: AuditLog model throws RuntimeException on update attempt.
     */
    public function test_audit_logs_cannot_be_updated(): void
    {
        $tenant = Tenant::factory()->create();
        TenantContext::setTenantId($tenant->id);

        $audit = AuditLog::factory()->create([
            'tenant_id' => $tenant->id,
            'action' => 'booking.created',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Audit logs are immutable and cannot be updated.');

        $audit->update(['action' => 'booking.tampered']);
    }

    /**
     * AC-008.2: AuditLog model throws RuntimeException on delete attempt.
     */
    public function test_audit_logs_cannot_be_deleted(): void
    {
        $tenant = Tenant::factory()->create();
        TenantContext::setTenantId($tenant->id);

        $audit = AuditLog::factory()->create([
            'tenant_id' => $tenant->id,
            'action' => 'booking.created',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Audit logs are immutable and cannot be deleted.');

        $audit->delete();
    }
}
