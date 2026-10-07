<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * Test successful booking creation via POST /api/v1/bookings returns 201 Created.
     */
    public function test_create_booking_successfully_returns_201(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->staff($tenant->id)->create([
            'telegram_user_id' => 99887766,
        ]);

        $item1 = Item::factory()->create(['tenant_id' => $tenant->id, 'rental_price' => 150.00]);
        $item2 = Item::factory()->create(['tenant_id' => $tenant->id, 'rental_price' => 200.00]);

        $pickup = now()->addDays(3)->format('Y-m-d');
        $event = now()->addDays(4)->format('Y-m-d');
        $return = now()->addDays(6)->format('Y-m-d');

        $payload = [
            'customer_name' => 'محمد علي',
            'customer_phone' => '0599123456',
            'pickup_date' => $pickup,
            'event_date' => $event,
            'return_date' => $return,
            'total_fee' => 350.00,
            'advance_paid' => 100.00,
            'payment_method' => 'cash',
            'alterations_notes' => 'تقصير البنطال 2 سم',
            'item_ids' => [$item1->id, $item2->id],
        ];

        $response = $this->withHeader('X-Telegram-User-Id', '99887766')
            ->postJson('/api/v1/bookings', $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'data' => [
                'customer_name' => 'محمد علي',
                'customer_phone' => '0599123456',
                'total_fee' => 350.00,
                'advance_paid' => 100.00,
                'remaining_balance' => 250.00,
                'collateral_status' => 'held',
            ],
        ]);

        $this->assertDatabaseHas('bookings', [
            'tenant_id' => $tenant->id,
            'customer_name' => 'محمد علي',
            'total_fee' => 350.00,
            'advance_paid' => 100.00,
            'remaining_balance' => 250.00,
        ]);

        $this->assertDatabaseHas('collateral_records', [
            'tenant_id' => $tenant->id,
            'status' => 'held',
        ]);
    }

    /**
     * Test booking conflict returns 409 Conflict.
     */
    public function test_create_booking_conflict_returns_409(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->staff($tenant->id)->create([
            'telegram_user_id' => 99887766,
        ]);

        $item = Item::factory()->create(['tenant_id' => $tenant->id]);

        $pickup = now()->addDays(3)->format('Y-m-d');
        $return = now()->addDays(6)->format('Y-m-d');

        $booking = Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'pickup_date' => $pickup,
            'return_date' => $return,
            'status' => 'active',
        ]);

        $booking->bookingItems()->create([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'rental_price' => 100.00,
        ]);

        // Attempt second booking for same item and dates
        $payload = [
            'customer_name' => 'عميل ثانٍ',
            'customer_phone' => '0599999999',
            'pickup_date' => $pickup,
            'return_date' => $return,
            'total_fee' => 100.00,
            'payment_method' => 'cash',
            'item_ids' => [$item->id],
        ];

        $response = $this->withHeader('X-Telegram-User-Id', '99887766')
            ->postJson('/api/v1/bookings', $payload);

        $response->assertStatus(409);
        $response->assertJson([
            'success' => false,
        ]);
        $response->assertJsonStructure(['conflicts']);
    }
}
