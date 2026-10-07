<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Tenant;
use App\Services\FinancialReportingService;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReportingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * AC-007.2: Daily Financial Report Accuracy with multi-method breakdown.
     */
    public function test_daily_report_calculation(): void
    {
        $today = Carbon::parse('2026-10-15 12:00:00');
        Carbon::setTestNow($today);

        $tenant = Tenant::factory()->create(['settings' => ['currency' => 'ILS']]);
        TenantContext::setTenantId($tenant->id);

        $b1 = Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'total_fee' => 300.00,
            'advance_paid' => 200.00,
            'remaining_balance' => 100.00,
            'status' => 'active',
            'created_at' => $today,
        ]);
        BookingPayment::factory()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $b1->id,
            'amount' => 200.00,
            'type' => 'advance',
            'method' => 'cash',
            'created_at' => $today,
        ]);

        $b2 = Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'total_fee' => 200.00,
            'advance_paid' => 100.00,
            'remaining_balance' => 100.00,
            'status' => 'active',
            'created_at' => $today,
        ]);
        BookingPayment::factory()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $b2->id,
            'amount' => 100.00,
            'type' => 'advance',
            'method' => 'palpay',
            'created_at' => $today,
        ]);

        $b3 = Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'total_fee' => 150.00,
            'advance_paid' => 0.00,
            'remaining_balance' => 0.00,
            'status' => 'completed',
            'created_at' => $today,
        ]);
        BookingPayment::factory()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $b3->id,
            'amount' => 150.00,
            'type' => 'final_payment',
            'method' => 'cash',
            'created_at' => $today,
        ]);
        BookingPayment::factory()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $b3->id,
            'amount' => 50.00,
            'type' => 'penalty',
            'method' => 'cash',
            'created_at' => $today,
        ]);

        $service = app(FinancialReportingService::class);
        $summary = $service->getDailySummary($today, $tenant->id);

        $this->assertEquals(500.00, $summary['total_revenue']);
        $this->assertEquals(400.00, $summary['revenue_by_method']['cash']);
        $this->assertEquals(100.00, $summary['revenue_by_method']['palpay']);
        $this->assertEquals(50.00, $summary['penalties_collected']);
        $this->assertEquals(200.00, $summary['total_receivables']);
        $this->assertEquals(3, $summary['bookings_count']);
        $this->assertEquals('ILS', $summary['currency']);

        Carbon::setTestNow();
    }
}
