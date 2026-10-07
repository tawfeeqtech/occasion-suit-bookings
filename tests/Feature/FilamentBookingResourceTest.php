<?php

namespace Tests\Feature;

use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\CollateralRecord;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentBookingResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * Test booking creation via Filament CreateBooking uses BookingService and sets collateral held.
     */
    public function test_can_create_booking_from_filament_panel(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->staff($tenant->id)->create();

        $this->actingAs($staff);
        TenantContext::setTenantId($tenant->id);

        $item1 = Item::factory()->create(['tenant_id' => $tenant->id, 'status' => 'available']);
        $item2 = Item::factory()->create(['tenant_id' => $tenant->id, 'status' => 'available']);

        $pickup = now()->addDays(2)->format('Y-m-d');
        $return = now()->addDays(5)->format('Y-m-d');

        Livewire::test(CreateBooking::class)
            ->fillForm([
                'customer_name' => 'سامي أحمد',
                'customer_phone' => '0599112233',
                'pickup_date' => $pickup,
                'return_date' => $return,
                'item_ids' => [$item1->id, $item2->id],
                'total_fee' => 400.00,
                'advance_paid' => 150.00,
                'payment_method' => 'cash',
                'alterations_notes' => 'تضييق الخصر',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('bookings', [
            'tenant_id' => $tenant->id,
            'customer_name' => 'سامي أحمد',
            'customer_phone' => '0599112233',
            'total_fee' => 400.00,
            'advance_paid' => 150.00,
            'remaining_balance' => 250.00,
            'status' => 'active',
        ]);

        $booking = Booking::where('customer_phone', '0599112233')->first();
        $this->assertNotNull($booking);
        $this->assertDatabaseHas('collateral_records', [
            'booking_id' => $booking->id,
            'status' => 'held',
        ]);
        $this->assertCount(2, $booking->bookingItems);
    }

    /**
     * Test process_return table action processes inspection and releases clean items to cleaning.
     */
    public function test_can_process_return_from_filament_table_action(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant->id)->create();

        $this->actingAs($owner);
        TenantContext::setTenantId($tenant->id);

        $booking = Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'total_fee' => 300.00,
            'advance_paid' => 100.00,
            'remaining_balance' => 200.00,
            'status' => 'active',
        ]);

        $item = Item::factory()->create(['tenant_id' => $tenant->id, 'status' => 'out_with_customer']);
        $bi = BookingItem::factory()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $booking->id,
            'item_id' => $item->id,
        ]);

        CollateralRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $booking->id,
            'status' => 'held',
        ]);

        Livewire::test(ListBookings::class)
            ->callTableAction('process_return', $booking, data: [
                'items' => [
                    [
                        'booking_item_id' => $bi->id,
                        'return_status' => 'clean_pass',
                        'penalty_fee' => 0.00,
                    ],
                ],
                'remaining_balance_collected' => 200.00,
                'penalty_collected' => 0.00,
                'payment_method' => 'cash',
                'release_collateral' => true,
            ])
            ->assertHasNoTableActionErrors();

        $booking->refresh();
        $this->assertEquals('completed', $booking->status);
        $this->assertEquals(0.00, (float) $booking->remaining_balance);

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'status' => 'cleaning',
        ]);

        $this->assertDatabaseHas('collateral_records', [
            'booking_id' => $booking->id,
            'status' => 'released',
        ]);
    }
}
