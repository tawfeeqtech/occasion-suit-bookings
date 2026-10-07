<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * Test checking availability for available items returns 200 with available: true.
     */
    public function test_check_availability_for_available_items(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->staff($tenant->id)->create([
            'telegram_user_id' => 11223344,
        ]);

        $item1 = Item::factory()->create(['tenant_id' => $tenant->id]);
        $item2 = Item::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->withHeader('X-Telegram-User-Id', '11223344')
            ->postJson('/api/v1/availability/check', [
                'item_ids' => [$item1->id, $item2->id],
                'pickup_date' => now()->addDays(5)->format('Y-m-d'),
                'return_date' => now()->addDays(8)->format('Y-m-d'),
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'available' => true,
        ]);
        $response->assertJsonCount(2, 'items');
    }

    /**
     * Test checking availability for conflicting items returns available: false and conflict details.
     */
    public function test_check_availability_returns_conflicts(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->staff($tenant->id)->create([
            'telegram_user_id' => 11223344,
        ]);

        $item = Item::factory()->create(['tenant_id' => $tenant->id]);

        $pickup = now()->addDays(5)->format('Y-m-d');
        $return = now()->addDays(8)->format('Y-m-d');

        $booking = Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'pickup_date' => $pickup,
            'return_date' => $return,
            'status' => 'active',
        ]);

        BookingItem::factory()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $booking->id,
            'item_id' => $item->id,
        ]);

        $response = $this->withHeader('X-Telegram-User-Id', '11223344')
            ->postJson('/api/v1/availability/check', [
                'item_ids' => [$item->id],
                'pickup_date' => $pickup,
                'return_date' => $return,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'available' => false,
        ]);
        $this->assertFalse($response->json('items.0.is_available'));
        $this->assertNotNull($response->json('items.0.conflict'));
    }

    /**
     * Test validation failure returns 422.
     */
    public function test_check_availability_validation_errors(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->staff($tenant->id)->create([
            'telegram_user_id' => 11223344,
        ]);

        $response = $this->withHeader('X-Telegram-User-Id', '11223344')
            ->postJson('/api/v1/availability/check', [
                'item_ids' => [],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['item_ids', 'pickup_date', 'return_date']);
    }
}
