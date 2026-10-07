<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Item;
use App\Models\ItemMaintenance;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AvailabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AvailabilityService::class);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * REQ-004-01: An unbooked item is returned as available.
     */
    public function test_item_is_available_when_no_bookings_or_maintenance_exist(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant->id)->create();
        $this->actingAs($owner);

        $item1 = Item::factory()->create(['tenant_id' => $tenant->id]);
        $item2 = Item::factory()->create(['tenant_id' => $tenant->id]);

        $pickup = Carbon::parse('2026-10-15');
        $return = Carbon::parse('2026-10-18');

        $result = $this->service->checkAvailability([$item1->id, $item2->id], $pickup, $return, $tenant->id);

        $this->assertTrue($result['available']);
        $this->assertCount(2, $result['items']);
        $this->assertTrue($result['items'][0]['is_available']);
        $this->assertTrue($result['items'][1]['is_available']);
    }

    /**
     * REQ-004-05: Detects direct date overlap with an active booking.
     */
    public function test_detects_unavailable_items_when_dates_overlap(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant->id)->create();
        $this->actingAs($owner);

        $item = Item::factory()->create(['tenant_id' => $tenant->id]);

        $existingBooking = Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'pickup_date' => '2026-10-15',
            'return_date' => '2026-10-18',
            'status' => 'active',
        ]);

        BookingItem::factory()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $existingBooking->id,
            'item_id' => $item->id,
        ]);

        // Attempt overlapping booking: 2026-10-16 to 2026-10-20
        $result = $this->service->checkAvailability(
            [$item->id],
            Carbon::parse('2026-10-16'),
            Carbon::parse('2026-10-20'),
            $tenant->id
        );

        $this->assertFalse($result['available']);
        $this->assertFalse($result['items'][0]['is_available']);
        $this->assertNotNull($result['items'][0]['conflict']);
        $this->assertNotNull($result['items'][0]['expected_ready_at']);
    }

    /**
     * REQ-005-03: Attempting to book an item during its cleaning buffer period is rejected.
     */
    public function test_item_cannot_be_booked_during_buffer_period(): void
    {
        // Tenant with 48 hours buffer (default)
        $tenant = Tenant::factory()->create([
            'settings' => ['buffer_hours' => 48, 'currency' => 'ILS'],
        ]);
        $owner = User::factory()->owner($tenant->id)->create();
        $this->actingAs($owner);

        $item = Item::factory()->create(['tenant_id' => $tenant->id]);

        // Existing booking ends on 2026-10-18 23:59:59.
        // With 48h buffer, item is ready on 2026-10-20 23:59:59.
        $existingBooking = Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'pickup_date' => '2026-10-15',
            'return_date' => '2026-10-18',
            'status' => 'active',
        ]);

        BookingItem::factory()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $existingBooking->id,
            'item_id' => $item->id,
        ]);

        // Try booking on 2026-10-19 (within 48 hours buffer!)
        $result = $this->service->checkAvailability(
            [$item->id],
            Carbon::parse('2026-10-19'),
            Carbon::parse('2026-10-22'),
            $tenant->id
        );

        $this->assertFalse($result['available']);
        $this->assertFalse($result['items'][0]['is_available']);
        $this->assertStringContainsString('فترة التنظيف', $result['items'][0]['conflict']);

        // Try booking on 2026-10-21 (after the buffer has elapsed)
        $resultAfterBuffer = $this->service->checkAvailability(
            [$item->id],
            Carbon::parse('2026-10-21'),
            Carbon::parse('2026-10-24'),
            $tenant->id
        );

        $this->assertTrue($resultAfterBuffer['available']);
        $this->assertTrue($resultAfterBuffer['items'][0]['is_available']);
    }

    /**
     * AC-005.2: Item in cleaning maintenance record is blocked when pickup is before ready date.
     */
    public function test_booking_rejected_when_item_in_active_maintenance(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant->id)->create();
        $this->actingAs($owner);

        $item = Item::factory()->cleaning()->create(['tenant_id' => $tenant->id]);

        ItemMaintenance::factory()->create([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'status' => 'cleaning',
            'expected_ready_at' => Carbon::parse('2026-10-20 18:00:00'),
        ]);

        // Pickup is requested for 2026-10-19 (before expected_ready_at)
        $result = $this->service->checkAvailability(
            [$item->id],
            Carbon::parse('2026-10-19'),
            Carbon::parse('2026-10-22'),
            $tenant->id
        );

        $this->assertFalse($result['available']);
        $this->assertFalse($result['items'][0]['is_available']);
        $this->assertStringContainsString('التنظيف والتعقيم', $result['items'][0]['conflict']);
    }
}
