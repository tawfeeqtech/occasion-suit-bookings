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

class BookingServiceTest extends TestCase
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
     * REQ-004-03 & REQ-004-04: Creates booking with manual pricing, payment, and held collateral record.
     */
    public function test_creates_booking_with_manual_pricing_and_held_collateral(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant->id)->create();
        $this->actingAs($owner);

        $item1 = Item::factory()->create(['tenant_id' => $tenant->id, 'rental_price' => 150.00]);
        $item2 = Item::factory()->create(['tenant_id' => $tenant->id, 'rental_price' => 200.00]);

        $booking = $this->service->createBooking([
            'tenant_id' => $tenant->id,
            'customer_name' => 'محمد علي',
            'customer_phone' => '0599123456',
            'pickup_date' => '2026-10-15',
            'event_date' => '2026-10-16',
            'return_date' => '2026-10-18',
            'total_fee' => 350.00,
            'advance_paid' => 100.00,
            'payment_method' => 'cash',
            'alterations_notes' => 'تقصير البنطال 2 سم',
            'item_ids' => [$item1->id, $item2->id],
        ], $owner);

        // Verify booking properties
        $this->assertInstanceOf(Booking::class, $booking);
        $this->assertEquals($tenant->id, $booking->tenant_id);
        $this->assertStringStartsWith('BK-', $booking->booking_number);
        $this->assertEquals('محمد علي', $booking->customer_name);
        $this->assertEquals(350.00, $booking->total_fee);
        $this->assertEquals(100.00, $booking->advance_paid);
        $this->assertEquals(250.00, $booking->remaining_balance);

        // Verify BookingItems
        $this->assertCount(2, $booking->bookingItems);
        $this->assertEquals(150.00, $booking->bookingItems->firstWhere('item_id', $item1->id)->rental_price);

        // Verify BookingPayment
        $this->assertCount(1, $booking->payments);
        $payment = $booking->payments->first();
        $this->assertEquals(100.00, $payment->amount);
        $this->assertEquals('advance', $payment->type);
        $this->assertEquals('cash', $payment->method);

        // Verify CollateralRecord (REQ-004-04: National IDs are never scanned; held status created)
        $this->assertNotNull($booking->collateralRecord);
        $this->assertEquals('held', $booking->collateralRecord->status);
        $this->assertNotNull($booking->collateralRecord->held_at);
        $this->assertNull($booking->collateralRecord->released_at);
    }

    /**
     * REQ-004-05: Cannot book an item that is already booked for the overlapping period.
     */
    public function test_cannot_book_overlapping_dates_throws_exception(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant->id)->create();
        $this->actingAs($owner);

        $item = Item::factory()->create(['tenant_id' => $tenant->id]);

        // First booking
        $this->service->createBooking([
            'tenant_id' => $tenant->id,
            'customer_name' => 'الزبون الأول',
            'customer_phone' => '0599000000',
            'pickup_date' => '2026-10-15',
            'return_date' => '2026-10-18',
            'total_fee' => 200.00,
            'advance_paid' => 50.00,
            'payment_method' => 'cash',
            'item_ids' => [$item->id],
        ], $owner);

        // Attempt second overlapping booking
        $this->expectException(BookingConflictException::class);

        $this->service->createBooking([
            'tenant_id' => $tenant->id,
            'customer_name' => 'الزبون الثاني',
            'customer_phone' => '0599111111',
            'pickup_date' => '2026-10-17',
            'return_date' => '2026-10-20',
            'total_fee' => 200.00,
            'advance_paid' => 0.00,
            'payment_method' => 'cash',
            'item_ids' => [$item->id],
        ], $owner);
    }

    /**
     * Test booking numbers increment sequentially per tenant.
     */
    public function test_booking_numbers_are_sequential(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant->id)->create();
        $this->actingAs($owner);

        $item1 = Item::factory()->create(['tenant_id' => $tenant->id]);
        $item2 = Item::factory()->create(['tenant_id' => $tenant->id]);

        $booking1 = $this->service->createBooking([
            'tenant_id' => $tenant->id,
            'customer_name' => 'عميل 1',
            'customer_phone' => '0599111111',
            'pickup_date' => '2026-10-01',
            'return_date' => '2026-10-03',
            'total_fee' => 100.00,
            'payment_method' => 'cash',
            'item_ids' => [$item1->id],
        ], $owner);

        $booking2 = $this->service->createBooking([
            'tenant_id' => $tenant->id,
            'customer_name' => 'عميل 2',
            'customer_phone' => '0599222222',
            'pickup_date' => '2026-10-05',
            'return_date' => '2026-10-07',
            'total_fee' => 100.00,
            'payment_method' => 'cash',
            'item_ids' => [$item2->id],
        ], $owner);

        $this->assertEquals('BK-'.date('Ym').'-0001', $booking1->booking_number);
        $this->assertEquals('BK-'.date('Ym').'-0002', $booking2->booking_number);
    }
}
