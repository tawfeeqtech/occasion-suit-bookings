<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\CollateralRecord;
use App\Models\Item;
use App\Models\ItemMaintenance;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BookingService;
use App\Services\ReturnService;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * AC-008.1: Automatic Booking Creation Audit Record.
     */
    public function test_booking_creation_records_audit_trail(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->staff($tenant->id)->create();
        TenantContext::setTenantId($tenant->id);

        $item = Item::factory()->create(['tenant_id' => $tenant->id, 'rental_price' => 200.00]);

        $bookingService = app(BookingService::class);
        $booking = $bookingService->createBooking([
            'customer_name' => 'خالد منصور',
            'customer_phone' => '0599123456',
            'pickup_date' => now()->addDays(2),
            'return_date' => now()->addDays(5),
            'total_fee' => 200.00,
            'advance_paid' => 50.00,
            'payment_method' => 'cash',
            'item_ids' => [$item->id],
        ], $staff);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'actor_type' => 'user',
            'actor_id' => (string) $staff->id,
            'action' => 'booking.created',
            'entity_type' => 'Booking',
            'entity_id' => $booking->id,
        ]);

        $audit = AuditLog::where('action', 'booking.created')->first();
        $this->assertNotNull($audit);
        $this->assertEquals('خالد منصور', $audit->metadata['customer_name']);
        $this->assertEquals(1, $audit->metadata['items_count']);
    }

    /**
     * AC-008.3: Collateral Release Audit Capture.
     */
    public function test_id_release_triggers_audit_entry(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->staff($tenant->id)->create();
        TenantContext::setTenantId($tenant->id);

        $booking = Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'remaining_balance' => 0.00,
            'status' => 'active',
        ]);

        $collateral = CollateralRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $booking->id,
            'status' => 'held',
        ]);

        $returnService = app(ReturnService::class);
        $returnService->releaseCollateral($booking, $staff, 'تسليم الهوية بعد سداد الحساب');

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'actor_type' => 'user',
            'actor_id' => (string) $staff->id,
            'action' => 'collateral.released',
            'entity_type' => 'CollateralRecord',
            'entity_id' => $collateral->id,
        ]);
    }

    /**
     * Penalty waiver triggers audit entry.
     */
    public function test_penalty_waiver_triggers_audit_entry(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant->id)->create();
        TenantContext::setTenantId($tenant->id);

        $booking = Booking::factory()->create(['tenant_id' => $tenant->id]);
        $item = Item::factory()->create(['tenant_id' => $tenant->id]);
        $bookingItem = BookingItem::factory()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $booking->id,
            'item_id' => $item->id,
            'inspection_status' => 'damaged',
            'penalty_fee' => 100.00,
            'is_waived' => false,
        ]);

        $returnService = app(ReturnService::class);
        $returnService->waivePenalty($bookingItem, $owner, 'خصم خاص للمحاربين القدامى');

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'actor_type' => 'user',
            'actor_id' => (string) $owner->id,
            'action' => 'penalty.waived',
            'entity_type' => 'BookingItem',
            'entity_id' => $bookingItem->id,
        ]);
    }

    /**
     * Cleaning buffer release command records system audit entry.
     */
    public function test_system_buffer_release_records_audit_entry(): void
    {
        $tenant = Tenant::factory()->create();
        $item = Item::factory()->create(['tenant_id' => $tenant->id, 'status' => 'cleaning']);

        ItemMaintenance::factory()->create([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'status' => 'cleaning',
            'expected_ready_at' => Carbon::now()->subHour(),
        ]);

        $this->artisan('buffer:release-clean-items')->assertExitCode(0);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'actor_type' => 'system',
            'actor_id' => 'scheduler',
            'action' => 'buffer.released',
            'entity_type' => 'ItemMaintenance',
        ]);
    }
}
