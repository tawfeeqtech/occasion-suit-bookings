<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\CollateralRecord;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollateralGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * AC-006.2: Release collateral blocked when penalty is unpaid.
     */
    public function test_release_collateral_blocked_when_penalty_unpaid(): void
    {
        $tenant = Tenant::factory()->create();
        TenantContext::setTenantId($tenant->id);

        $staff = User::factory()->staff($tenant->id)->create([
            'telegram_user_id' => 12345678,
        ]);

        $booking = Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'remaining_balance' => 0.00,
            'status' => 'damage_pending',
        ]);

        $item = Item::factory()->create(['tenant_id' => $tenant->id, 'status' => 'maintenance']);

        BookingItem::factory()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $booking->id,
            'item_id' => $item->id,
            'inspection_status' => 'damaged',
            'penalty_fee' => 200.00,
            'penalty_reason' => 'تمزق في القماش',
            'is_waived' => false,
        ]);

        CollateralRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $booking->id,
            'status' => 'held',
        ]);

        $response = $this->withHeader('X-Telegram-User-Id', '12345678')
            ->postJson("/api/v1/bookings/{$booking->id}/collateral/release");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['collateral']);
        $this->assertStringContainsString('لا يمكن تسليم بطاقة الهوية قبل سداد الغرامة المستحقة', $response->json('errors.collateral.0'));

        $this->assertDatabaseHas('collateral_records', [
            'booking_id' => $booking->id,
            'status' => 'held',
        ]);
    }

    /**
     * REQ-006-07: Cannot release ID while rental balance or unwaived penalties remain unpaid.
     */
    public function test_cannot_release_id_with_outstanding_balance_or_penalties(): void
    {
        $tenant = Tenant::factory()->create();
        TenantContext::setTenantId($tenant->id);

        $staff = User::factory()->staff($tenant->id)->create([
            'telegram_user_id' => 12345678,
        ]);

        // Case 1: Both balance and penalty outstanding
        $bookingBoth = Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'remaining_balance' => 150.00,
            'status' => 'damage_pending',
        ]);

        $item1 = Item::factory()->create(['tenant_id' => $tenant->id]);
        BookingItem::factory()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $bookingBoth->id,
            'item_id' => $item1->id,
            'inspection_status' => 'damaged',
            'penalty_fee' => 100.00,
            'penalty_reason' => 'تلف الزر',
            'is_waived' => false,
        ]);

        CollateralRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $bookingBoth->id,
            'status' => 'held',
        ]);

        $responseBoth = $this->withHeader('X-Telegram-User-Id', '12345678')
            ->postJson("/api/v1/bookings/{$bookingBoth->id}/collateral/release");

        $responseBoth->assertStatus(422);
        $this->assertStringContainsString('لا يمكن تسليم بطاقة الهوية قبل سداد الغرامة المستحقة والرصيد المتبقي', $responseBoth->json('errors.collateral.0'));

        // Case 2: Only rental balance outstanding
        $bookingBalanceOnly = Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'remaining_balance' => 100.00,
            'status' => 'active',
        ]);

        $item2 = Item::factory()->create(['tenant_id' => $tenant->id]);
        BookingItem::factory()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $bookingBalanceOnly->id,
            'item_id' => $item2->id,
            'inspection_status' => 'clean_pass',
            'penalty_fee' => 0.00,
            'is_waived' => false,
        ]);

        CollateralRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $bookingBalanceOnly->id,
            'status' => 'held',
        ]);

        $responseBalanceOnly = $this->withHeader('X-Telegram-User-Id', '12345678')
            ->postJson("/api/v1/bookings/{$bookingBalanceOnly->id}/collateral/release");

        $responseBalanceOnly->assertStatus(422);
        $this->assertStringContainsString('لا يمكن تسليم بطاقة الهوية قبل سداد الرصيد المتبقي', $responseBalanceOnly->json('errors.collateral.0'));
    }

    /**
     * REQ-006-05: Settling penalty and balances allows ID release.
     */
    public function test_settling_penalty_allows_id_release(): void
    {
        $tenant = Tenant::factory()->create();
        TenantContext::setTenantId($tenant->id);

        $staff = User::factory()->staff($tenant->id)->create([
            'telegram_user_id' => 12345678,
        ]);

        $booking = Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'remaining_balance' => 0.00,
            'status' => 'damage_pending',
        ]);

        $item = Item::factory()->create(['tenant_id' => $tenant->id, 'status' => 'maintenance']);

        BookingItem::factory()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $booking->id,
            'item_id' => $item->id,
            'inspection_status' => 'damaged',
            'penalty_fee' => 200.00,
            'penalty_reason' => 'حرق بالمكواة',
            'is_waived' => false,
        ]);

        $collateral = CollateralRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $booking->id,
            'status' => 'held',
        ]);

        // Pay the penalty
        $payResponse = $this->withHeader('X-Telegram-User-Id', '12345678')
            ->postJson("/api/v1/bookings/{$booking->id}/pay-penalty", [
                'amount' => 200.00,
                'payment_method' => 'cash',
            ]);
        $payResponse->assertStatus(200);

        // Now release collateral
        $releaseResponse = $this->withHeader('X-Telegram-User-Id', '12345678')
            ->postJson("/api/v1/bookings/{$booking->id}/collateral/release");

        $releaseResponse->assertStatus(200);
        $this->assertEquals('released', $releaseResponse->json('data.status'));

        $collateral->refresh();
        $this->assertEquals('released', $collateral->status);
        $this->assertEquals($staff->id, $collateral->released_by);

        $booking->refresh();
        $this->assertEquals('completed', $booking->status);
    }

    /**
     * AC-006.5: Owner-approved waiver allows ID release.
     */
    public function test_owner_waiving_penalty_allows_id_release(): void
    {
        $tenant = Tenant::factory()->create();
        TenantContext::setTenantId($tenant->id);

        $owner = User::factory()->owner($tenant->id)->create([
            'telegram_user_id' => 88889999,
        ]);

        $booking = Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'remaining_balance' => 0.00,
            'status' => 'damage_pending',
        ]);

        $item = Item::factory()->create(['tenant_id' => $tenant->id]);

        $bookingItem = BookingItem::factory()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $booking->id,
            'item_id' => $item->id,
            'inspection_status' => 'damaged',
            'penalty_fee' => 150.00,
            'penalty_reason' => 'تمزق بسيط',
            'is_waived' => false,
        ]);

        CollateralRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $booking->id,
            'status' => 'held',
        ]);

        // Owner waives the penalty
        $waiveResponse = $this->withHeader('X-Telegram-User-Id', '88889999')
            ->postJson("/api/v1/bookings/{$booking->id}/items/{$bookingItem->id}/waive-penalty", [
                'reason' => 'إعفاء بطلب من المالك لعميل مميز',
            ]);

        $waiveResponse->assertStatus(200);

        $bookingItem->refresh();
        $this->assertTrue($bookingItem->is_waived);

        // Now release collateral succeeds
        $releaseResponse = $this->withHeader('X-Telegram-User-Id', '88889999')
            ->postJson("/api/v1/bookings/{$booking->id}/collateral/release");

        $releaseResponse->assertStatus(200);
        $this->assertEquals('released', $releaseResponse->json('data.status'));
    }

    /**
     * Staff cannot waive penalty (Owner only).
     */
    public function test_staff_cannot_waive_penalty(): void
    {
        $tenant = Tenant::factory()->create();
        TenantContext::setTenantId($tenant->id);

        $staff = User::factory()->staff($tenant->id)->create([
            'telegram_user_id' => 77776666,
        ]);

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

        $response = $this->withHeader('X-Telegram-User-Id', '77776666')
            ->postJson("/api/v1/bookings/{$booking->id}/items/{$bookingItem->id}/waive-penalty", [
                'reason' => 'محاولة إعفاء من موظف عادي',
            ]);

        $response->assertStatus(403);
    }
}
