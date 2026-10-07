<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\CollateralRecord;
use App\Models\Item;
use App\Models\ItemMaintenance;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ReturnService;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * AC-006.1: Clean Pass Return Execution
     * Booking with 3 items, total fee 500, advance paid 200 (balance 300).
     * When return submitted marking all 3 clean_pass, remaining 300 collected, release_collateral = true.
     * Then booking becomes completed, items enter cleaning, and collateral is released.
     */
    public function test_clean_pass_return_completes_booking_and_releases_collateral(): void
    {
        $tenant = Tenant::factory()->create(['settings' => ['buffer_hours' => 48]]);
        TenantContext::setTenantId($tenant->id);

        $staff = User::factory()->staff($tenant->id)->create();

        $booking = Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'total_fee' => 500.00,
            'advance_paid' => 200.00,
            'remaining_balance' => 300.00,
            'status' => 'active',
        ]);

        $item1 = Item::factory()->create(['tenant_id' => $tenant->id, 'status' => 'out_with_customer']);
        $item2 = Item::factory()->create(['tenant_id' => $tenant->id, 'status' => 'out_with_customer']);
        $item3 = Item::factory()->create(['tenant_id' => $tenant->id, 'status' => 'out_with_customer']);

        $bi1 = BookingItem::factory()->create(['tenant_id' => $tenant->id, 'booking_id' => $booking->id, 'item_id' => $item1->id]);
        $bi2 = BookingItem::factory()->create(['tenant_id' => $tenant->id, 'booking_id' => $booking->id, 'item_id' => $item2->id]);
        $bi3 = BookingItem::factory()->create(['tenant_id' => $tenant->id, 'booking_id' => $booking->id, 'item_id' => $item3->id]);

        $collateral = CollateralRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $booking->id,
            'status' => 'held',
        ]);

        $returnService = app(ReturnService::class);

        $payload = [
            'items' => [
                ['booking_item_id' => $bi1->id, 'return_status' => 'clean_pass'],
                ['booking_item_id' => $bi2->id, 'return_status' => 'clean_pass'],
                ['booking_item_id' => $bi3->id, 'return_status' => 'clean_pass'],
            ],
            'remaining_balance_collected' => 300.00,
            'payment_method' => 'cash',
            'release_collateral' => true,
        ];

        $updatedBooking = $returnService->processReturn($booking, $payload, $staff);

        $this->assertEquals('completed', $updatedBooking->status);
        $this->assertEquals(0.00, (float) $updatedBooking->remaining_balance);

        $this->assertDatabaseHas('items', ['id' => $item1->id, 'status' => 'cleaning']);
        $this->assertDatabaseHas('items', ['id' => $item2->id, 'status' => 'cleaning']);
        $this->assertDatabaseHas('items', ['id' => $item3->id, 'status' => 'cleaning']);

        $this->assertDatabaseHas('collateral_records', [
            'id' => $collateral->id,
            'status' => 'released',
            'released_by' => $staff->id,
        ]);

        $this->assertDatabaseHas('booking_payments', [
            'booking_id' => $booking->id,
            'amount' => 300.00,
            'type' => 'final_payment',
        ]);
    }

    /**
     * AC-005.1: Automatic Maintenance Record on Return with tenant configured buffer_hours.
     */
    public function test_item_maintenance_record_created_with_48h_offset(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00 UTC'));

        $tenant = Tenant::factory()->create(['settings' => ['buffer_hours' => 48]]);
        TenantContext::setTenantId($tenant->id);

        $staff = User::factory()->staff($tenant->id)->create();

        $booking = Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'remaining_balance' => 0.00,
            'status' => 'active',
        ]);

        $item = Item::factory()->create(['tenant_id' => $tenant->id, 'status' => 'out_with_customer']);
        $bi = BookingItem::factory()->create(['tenant_id' => $tenant->id, 'booking_id' => $booking->id, 'item_id' => $item->id]);

        $returnService = app(ReturnService::class);

        $returnService->processReturn($booking, [
            'items' => [
                ['booking_item_id' => $bi->id, 'return_status' => 'clean_pass'],
            ],
            'release_collateral' => false,
        ], $staff);

        $maintenance = ItemMaintenance::where('item_id', $item->id)->first();
        $this->assertNotNull($maintenance);
        $this->assertEquals($tenant->id, $maintenance->tenant_id);
        $this->assertEquals('cleaning', $maintenance->status);
        $this->assertEquals(Carbon::now()->addHours(48)->timestamp, $maintenance->expected_ready_at->timestamp);

        Carbon::setTestNow();
    }

    /**
     * REQ-005-01: Returned clean item enters cleaning status immediately.
     */
    public function test_returned_item_enters_cleaning_status(): void
    {
        $tenant = Tenant::factory()->create();
        TenantContext::setTenantId($tenant->id);

        $staff = User::factory()->staff($tenant->id)->create();

        $booking = Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'remaining_balance' => 0.00,
            'status' => 'active',
        ]);

        $item = Item::factory()->create(['tenant_id' => $tenant->id, 'status' => 'out_with_customer']);
        $bi = BookingItem::factory()->create(['tenant_id' => $tenant->id, 'booking_id' => $booking->id, 'item_id' => $item->id]);

        $returnService = app(ReturnService::class);

        $returnService->processReturn($booking, [
            'items' => [
                ['booking_item_id' => $bi->id, 'return_status' => 'clean_pass'],
            ],
        ], $staff);

        $item->refresh();
        $this->assertEquals('cleaning', $item->status);
    }
}
