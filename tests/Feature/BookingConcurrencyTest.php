<?php

namespace Tests\Feature;

use App\Exceptions\BookingConflictException;
use App\Models\Booking;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BookingService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected BookingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BookingService::class);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * REQ-004-02: Simultaneous booking attempts for the same item prevent double-booking.
     */
    public function test_simultaneous_booking_requests_prevent_double_booking(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant->id)->create();
        $this->actingAs($owner);

        $item = Item::factory()->create(['tenant_id' => $tenant->id]);

        $firstSuccess = false;
        $secondSuccess = false;
        $conflictCaught = false;

        // Simulate request 1
        try {
            $booking1 = $this->service->createBooking([
                'tenant_id' => $tenant->id,
                'customer_name' => 'المستأجر الأول',
                'customer_phone' => '0599111222',
                'pickup_date' => '2026-10-15',
                'return_date' => '2026-10-18',
                'total_fee' => 250.00,
                'advance_paid' => 50.00,
                'payment_method' => 'cash',
                'item_ids' => [$item->id],
            ], $owner);
            $firstSuccess = (bool) $booking1;
        } catch (\Exception $e) {
            $firstSuccess = false;
        }

        // Simulate concurrent request 2 attempting the same item for overlapping period
        try {
            $booking2 = $this->service->createBooking([
                'tenant_id' => $tenant->id,
                'customer_name' => 'المستأجر الثاني',
                'customer_phone' => '0599333444',
                'pickup_date' => '2026-10-16',
                'return_date' => '2026-10-19',
                'total_fee' => 250.00,
                'advance_paid' => 50.00,
                'payment_method' => 'cash',
                'item_ids' => [$item->id],
            ], $owner);
            $secondSuccess = (bool) $booking2;
        } catch (BookingConflictException $e) {
            $conflictCaught = true;
        }

        $this->assertTrue($firstSuccess, 'The first booking must succeed.');
        $this->assertFalse($secondSuccess, 'The competing concurrent booking must fail.');
        $this->assertTrue($conflictCaught, 'A BookingConflictException must be thrown.');

        // Verify in database: exactly 1 booking exists
        $this->assertEquals(1, Booking::where('tenant_id', $tenant->id)->count());
    }
}
