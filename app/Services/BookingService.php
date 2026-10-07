<?php

namespace App\Services;

use App\Exceptions\BookingConflictException;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingPayment;
use App\Models\CollateralRecord;
use App\Models\Item;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        protected AvailabilityService $availabilityService
    ) {}

    /**
     * Create a new booking transactionally with pessimistic row locking.
     *
     * @param array{
     *     tenant_id?: string|null,
     *     customer_name: string,
     *     customer_phone: string,
     *     pickup_date: string|Carbon,
     *     event_date?: string|Carbon|null,
     *     return_date: string|Carbon,
     *     total_fee: float|int|string,
     *     advance_paid?: float|int|string|null,
     *     payment_method: string,
     *     alterations_notes?: string|null,
     *     item_ids: list<string>,
     *     item_prices?: array<string, float|int|string>|null
     * } $data
     *
     * @throws BookingConflictException
     */
    public function createBooking(array $data, ?User $creator = null): Booking
    {
        $tenantId = $data['tenant_id'] ?? TenantContext::getTenantId() ?? $creator?->tenant_id;

        if (! $tenantId) {
            throw new \InvalidArgumentException('Tenant ID is required to create a booking.');
        }

        // Sort item IDs ascending to prevent database deadlocks across concurrent requests
        $itemIds = collect($data['item_ids'])
            ->unique()
            ->sort()
            ->values()
            ->all();

        if (empty($itemIds)) {
            throw new \InvalidArgumentException('At least one item must be selected for booking.');
        }

        $pickupDate = Carbon::parse($data['pickup_date'])->startOfDay();
        $returnDate = Carbon::parse($data['return_date'])->endOfDay();

        return DB::transaction(function () use ($data, $tenantId, $itemIds, $pickupDate, $returnDate, $creator) {
            // 1. Lock items in ascending order using SELECT ... FOR UPDATE
            $lockedItems = Item::whereIn('id', $itemIds)
                ->where('tenant_id', $tenantId)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            if ($lockedItems->count() !== count($itemIds)) {
                throw new BookingConflictException('One or more selected items do not exist in inventory.', [
                    ['id' => 'missing_items', 'reason' => 'Some items were not found or belong to another tenant.'],
                ]);
            }

            // 2. Perform availability check inside transaction while holding the locks
            $availability = $this->availabilityService->checkAvailability(
                $itemIds,
                $pickupDate,
                $returnDate,
                $tenantId
            );

            if (! $availability['available']) {
                $conflicts = array_filter($availability['items'], fn ($item) => ! $item['is_available']);

                throw new BookingConflictException(
                    'Selected items are not available for the requested period.',
                    array_values($conflicts)
                );
            }

            // 3. Generate sequential booking number (e.g. BK-202610-0001)
            $bookingNumber = $this->generateBookingNumber($tenantId);

            // 4. Calculate pricing
            $totalFee = (float) $data['total_fee'];
            $advancePaid = isset($data['advance_paid']) ? (float) $data['advance_paid'] : 0.00;
            $remainingBalance = max(0.00, $totalFee - $advancePaid);

            // 5. Create Booking record
            $booking = Booking::create([
                'tenant_id' => $tenantId,
                'booking_number' => $bookingNumber,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'pickup_date' => $pickupDate->toDateString(),
                'event_date' => ! empty($data['event_date']) ? Carbon::parse($data['event_date'])->toDateString() : null,
                'return_date' => $returnDate->toDateString(),
                'status' => 'active',
                'total_fee' => $totalFee,
                'advance_paid' => $advancePaid,
                'remaining_balance' => $remainingBalance,
                'payment_method' => $data['payment_method'],
                'alterations_notes' => $data['alterations_notes'] ?? null,
                'created_by' => $creator?->id,
            ]);

            // 6. Create BookingItem records
            $itemPrices = $data['item_prices'] ?? [];
            foreach ($lockedItems as $item) {
                $rentalPrice = isset($itemPrices[$item->id])
                    ? (float) $itemPrices[$item->id]
                    : (float) $item->rental_price;

                BookingItem::create([
                    'tenant_id' => $tenantId,
                    'booking_id' => $booking->id,
                    'item_id' => $item->id,
                    'rental_price' => $rentalPrice,
                    'inspection_status' => 'clean_pass',
                    'penalty_fee' => 0.00,
                    'is_waived' => false,
                ]);
            }

            // 7. Record advance payment if paid
            if ($advancePaid > 0) {
                BookingPayment::create([
                    'tenant_id' => $tenantId,
                    'booking_id' => $booking->id,
                    'amount' => $advancePaid,
                    'type' => 'advance',
                    'method' => $data['payment_method'],
                    'reference_number' => $data['reference_number'] ?? null,
                    'recorded_by' => $creator?->id,
                ]);
            }

            // 8. Create CollateralRecord with status 'held'
            CollateralRecord::create([
                'tenant_id' => $tenantId,
                'booking_id' => $booking->id,
                'status' => 'held',
                'notes' => 'Collateral held at booking confirmation',
                'held_at' => now(),
            ]);

            return $booking->fresh(['bookingItems.item', 'payments', 'collateralRecord']);
        });
    }

    /**
     * Generate sequential unique booking number per tenant.
     */
    protected function generateBookingNumber(string $tenantId): string
    {
        $prefix = 'BK-'.date('Ym').'-';

        $latestNumber = Booking::where('tenant_id', $tenantId)
            ->where('booking_number', 'like', $prefix.'%')
            ->orderBy('booking_number', 'desc')
            ->lockForUpdate()
            ->value('booking_number');

        if ($latestNumber) {
            $seq = (int) substr($latestNumber, -4);
            $nextSeq = $seq + 1;
        } else {
            $nextSeq = 1;
        }

        return $prefix.str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
    }
}
