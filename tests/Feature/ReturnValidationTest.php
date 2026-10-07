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

class ReturnValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * AC-006.3: Partial return validation rejects incomplete item checklist.
     */
    public function test_incomplete_item_checklist_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        TenantContext::setTenantId($tenant->id);

        User::factory()->staff($tenant->id)->create([
            'telegram_user_id' => 55554444,
        ]);

        $booking = Booking::factory()->create(['tenant_id' => $tenant->id]);

        $item1 = Item::factory()->create(['tenant_id' => $tenant->id]);
        $item2 = Item::factory()->create(['tenant_id' => $tenant->id]);
        $item3 = Item::factory()->create(['tenant_id' => $tenant->id]);
        $item4 = Item::factory()->create(['tenant_id' => $tenant->id]);

        $bi1 = BookingItem::factory()->create(['tenant_id' => $tenant->id, 'booking_id' => $booking->id, 'item_id' => $item1->id]);
        $bi2 = BookingItem::factory()->create(['tenant_id' => $tenant->id, 'booking_id' => $booking->id, 'item_id' => $item2->id]);
        $bi3 = BookingItem::factory()->create(['tenant_id' => $tenant->id, 'booking_id' => $booking->id, 'item_id' => $item3->id]);
        BookingItem::factory()->create(['tenant_id' => $tenant->id, 'booking_id' => $booking->id, 'item_id' => $item4->id]);

        // Submit return with only 3 of the 4 items
        $payload = [
            'items' => [
                ['booking_item_id' => $bi1->id, 'return_status' => 'clean_pass'],
                ['booking_item_id' => $bi2->id, 'return_status' => 'clean_pass'],
                ['booking_item_id' => $bi3->id, 'return_status' => 'clean_pass'],
            ],
        ];

        $response = $this->withHeader('X-Telegram-User-Id', '55554444')
            ->postJson("/api/v1/bookings/{$booking->id}/return", $payload);

        $response->assertStatus(422);
        $this->assertStringContainsString('يجب تحديد حالة جميع عناصر الحجز لإتمام الإرجاع', $response->json('errors.items.0'));
    }

    /**
     * REQ-006-02: Every item must have a valid return_status.
     */
    public function test_all_items_must_have_inspection_status(): void
    {
        $tenant = Tenant::factory()->create();
        TenantContext::setTenantId($tenant->id);

        User::factory()->staff($tenant->id)->create([
            'telegram_user_id' => 55554444,
        ]);

        $booking = Booking::factory()->create(['tenant_id' => $tenant->id]);
        $item = Item::factory()->create(['tenant_id' => $tenant->id]);
        $bi = BookingItem::factory()->create(['tenant_id' => $tenant->id, 'booking_id' => $booking->id, 'item_id' => $item->id]);

        $payload = [
            'items' => [
                ['booking_item_id' => $bi->id, 'return_status' => 'invalid_status'],
            ],
        ];

        $response = $this->withHeader('X-Telegram-User-Id', '55554444')
            ->postJson("/api/v1/bookings/{$booking->id}/return", $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items.0.return_status']);
    }

    /**
     * REQ-006-04: Damaged/missing items require manual penalty fee and reason.
     */
    public function test_damaged_item_requires_penalty_and_reason(): void
    {
        $tenant = Tenant::factory()->create();
        TenantContext::setTenantId($tenant->id);

        User::factory()->staff($tenant->id)->create([
            'telegram_user_id' => 55554444,
        ]);

        $booking = Booking::factory()->create(['tenant_id' => $tenant->id]);
        $item = Item::factory()->create(['tenant_id' => $tenant->id]);
        $bi = BookingItem::factory()->create(['tenant_id' => $tenant->id, 'booking_id' => $booking->id, 'item_id' => $item->id]);

        $payload = [
            'items' => [
                [
                    'booking_item_id' => $bi->id,
                    'return_status' => 'damaged',
                    // missing penalty_fee and penalty_reason
                ],
            ],
        ];

        $response = $this->withHeader('X-Telegram-User-Id', '55554444')
            ->postJson("/api/v1/bookings/{$booking->id}/return", $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items.0.penalty_fee', 'items.0.penalty_reason']);
    }

    /**
     * AC-006.4: Manual penalty amount and reason are recorded accurately.
     */
    public function test_manual_penalty_amount_and_reason_are_recorded(): void
    {
        $tenant = Tenant::factory()->create();
        TenantContext::setTenantId($tenant->id);

        User::factory()->staff($tenant->id)->create([
            'telegram_user_id' => 55554444,
        ]);

        $booking = Booking::factory()->create(['tenant_id' => $tenant->id]);
        $item = Item::factory()->create(['tenant_id' => $tenant->id]);
        $bi = BookingItem::factory()->create(['tenant_id' => $tenant->id, 'booking_id' => $booking->id, 'item_id' => $item->id]);

        $payload = [
            'items' => [
                [
                    'booking_item_id' => $bi->id,
                    'return_status' => 'damaged',
                    'penalty_fee' => 175.50,
                    'penalty_reason' => 'تمزق السحاب الرئيسي واستبدال البطانة',
                    'notes' => 'يحتاج خياطة فورية',
                ],
            ],
            'release_collateral' => false,
        ];

        $response = $this->withHeader('X-Telegram-User-Id', '55554444')
            ->postJson("/api/v1/bookings/{$booking->id}/return", $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('booking_items', [
            'id' => $bi->id,
            'inspection_status' => 'damaged',
            'penalty_fee' => 175.50,
            'penalty_reason' => 'تمزق السحاب الرئيسي واستبدال البطانة',
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'damage_pending',
        ]);
    }
}
