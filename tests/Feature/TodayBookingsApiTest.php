<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TodayBookingsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * Test retrieving today's pickups and scheduled returns.
     */
    public function test_get_today_bookings_schedule(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->staff($tenant->id)->create([
            'telegram_user_id' => 77889900,
        ]);

        $today = Carbon::today()->toDateString();
        $tomorrow = Carbon::tomorrow()->toDateString();

        // 1. Pickup today
        $pickupBooking = Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_name' => 'زبون استلام اليوم',
            'pickup_date' => $today,
            'return_date' => $tomorrow,
            'status' => 'active',
        ]);

        // 2. Return today
        $returnBooking = Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_name' => 'زبون إرجاع اليوم',
            'pickup_date' => Carbon::yesterday()->toDateString(),
            'return_date' => $today,
            'status' => 'active',
        ]);

        // 3. Booking on different date (not today)
        Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_name' => 'زبون موعد آخر',
            'pickup_date' => now()->addDays(5)->toDateString(),
            'return_date' => now()->addDays(8)->toDateString(),
            'status' => 'active',
        ]);

        $response = $this->withHeader('X-Telegram-User-Id', '77889900')
            ->getJson('/api/v1/bookings/today');

        $response->assertStatus(200);
        $response->assertJson([
            'date' => $today,
            'pickups_today_count' => 1,
            'returns_today_count' => 1,
        ]);

        $response->assertJsonFragment(['customer_name' => 'زبون استلام اليوم']);
        $response->assertJsonFragment(['customer_name' => 'زبون إرجاع اليوم']);
        $response->assertJsonMissing(['customer_name' => 'زبون موعد آخر']);
    }
}
