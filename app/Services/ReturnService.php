<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingPayment;
use App\Models\CollateralRecord;
use App\Models\ItemMaintenance;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnService
{
    /**
     * Process return of booking items, inspecting all bundle items and managing cleaning/damage.
     *
     * @param array{
     *     items: list<array{
     *         booking_item_id: string,
     *         return_status: 'clean_pass'|'damaged'|'missing',
     *         penalty_fee?: float|int|string|null,
     *         penalty_reason?: string|null,
     *         notes?: string|null,
     *         damage_notes?: string|null
     *     }>,
     *     remaining_balance_collected?: float|int|string|null,
     *     penalty_collected?: float|int|string|null,
     *     payment_method?: string|null,
     *     release_collateral?: bool|null
     * } $data
     *
     * @throws ValidationException
     */
    public function processReturn(Booking $booking, array $data, ?User $actor = null): Booking
    {
        $tenantId = $booking->tenant_id;
        TenantContext::setTenantId($tenantId);

        // 1. Verify complete checklist: every booking item must be explicitly inspected
        $bookingItems = $booking->bookingItems()->get()->keyBy('id');
        $submittedItems = collect($data['items'] ?? [])->keyBy('booking_item_id');

        if ($submittedItems->count() !== $bookingItems->count() || $submittedItems->keys()->diff($bookingItems->keys())->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => ['يجب تحديد حالة جميع عناصر الحجز لإتمام الإرجاع'],
            ]);
        }

        // 2. Fetch tenant cleaning buffer hours
        $tenant = Tenant::find($tenantId);
        $bufferHours = 48;
        if ($tenant && isset($tenant->settings['buffer_hours'])) {
            $bufferHours = (int) $tenant->settings['buffer_hours'];
        }

        return DB::transaction(function () use ($booking, $bookingItems, $submittedItems, $data, $actor, $tenantId, $bufferHours) {
            $hasDamageOrMissing = false;

            // 3. Process each inspected item
            foreach ($submittedItems as $bookingItemId => $itemData) {
                /** @var BookingItem $bookingItem */
                $bookingItem = $bookingItems->get($bookingItemId);
                $status = $itemData['return_status'] ?? null;

                if (! in_array($status, ['clean_pass', 'damaged', 'missing'], true)) {
                    throw ValidationException::withMessages([
                        'items' => ['حالة الفحص غير صحيحة. يجب أن تكون: clean_pass أو damaged أو missing.'],
                    ]);
                }

                $notes = $itemData['notes'] ?? $itemData['damage_notes'] ?? null;

                if ($status === 'clean_pass') {
                    $bookingItem->update([
                        'inspection_status' => 'clean_pass',
                        'penalty_fee' => 0.00,
                        'penalty_reason' => null,
                        'damage_notes' => $notes,
                        'is_waived' => false,
                    ]);

                    // Update inventory item to cleaning
                    $bookingItem->item()->update(['status' => 'cleaning']);

                    // Insert item_maintenance record
                    ItemMaintenance::create([
                        'tenant_id' => $tenantId,
                        'item_id' => $bookingItem->item_id,
                        'booking_id' => $booking->id,
                        'status' => 'cleaning',
                        'started_at' => Carbon::now(),
                        'expected_ready_at' => Carbon::now()->addHours($bufferHours),
                        'notes' => 'تنظيف دوري بعد الإرجاع',
                    ]);
                } else {
                    // Damaged or missing items require manual penalty amount and reason
                    $penaltyFee = isset($itemData['penalty_fee']) ? (float) $itemData['penalty_fee'] : 0.00;
                    $penaltyReason = $itemData['penalty_reason'] ?? null;

                    if ($penaltyFee <= 0 || empty($penaltyReason)) {
                        throw ValidationException::withMessages([
                            'penalty' => ['مبلغ وسبب الغرامة مطلوبان في حال التلف أو الفقدان'],
                        ]);
                    }

                    $bookingItem->update([
                        'inspection_status' => $status,
                        'penalty_fee' => $penaltyFee,
                        'penalty_reason' => $penaltyReason,
                        'damage_notes' => $notes,
                        'is_waived' => false,
                    ]);

                    // Transition item to maintenance
                    $bookingItem->item()->update(['status' => 'maintenance']);

                    $hasDamageOrMissing = true;
                }
            }

            // 4. Record any collected balance payments
            $paymentMethod = $data['payment_method'] ?? 'cash';

            if (! empty($data['remaining_balance_collected']) && (float) $data['remaining_balance_collected'] > 0) {
                $collectedBalance = (float) $data['remaining_balance_collected'];
                $booking->remaining_balance = max(0.00, (float) $booking->remaining_balance - $collectedBalance);

                BookingPayment::create([
                    'tenant_id' => $tenantId,
                    'booking_id' => $booking->id,
                    'amount' => $collectedBalance,
                    'type' => 'final_payment',
                    'method' => $paymentMethod,
                    'recorded_by' => $actor?->id,
                ]);
            }

            // 5. Record any collected penalty payments
            if (! empty($data['penalty_collected']) && (float) $data['penalty_collected'] > 0) {
                $collectedPenalty = (float) $data['penalty_collected'];

                BookingPayment::create([
                    'tenant_id' => $tenantId,
                    'booking_id' => $booking->id,
                    'amount' => $collectedPenalty,
                    'type' => 'penalty',
                    'method' => $paymentMethod,
                    'recorded_by' => $actor?->id,
                ]);
            }

            // 6. Update booking status
            $hasUnpaidPenalties = $this->hasOutstandingPenalties($booking);

            if ($hasDamageOrMissing || $hasUnpaidPenalties) {
                $booking->status = 'damage_pending';
            } elseif ((float) $booking->remaining_balance == 0.00) {
                $booking->status = 'completed';
            }
            $booking->save();

            // 7. Handle collateral release request if requested
            if (! empty($data['release_collateral'])) {
                $this->releaseCollateral($booking, $actor);
            }

            return $booking->fresh(['bookingItems.item', 'payments', 'collateralRecord']);
        });
    }

    /**
     * Check if a booking has unpaid and unwaived penalties.
     */
    public function hasOutstandingPenalties(Booking $booking): bool
    {
        $totalPenalties = (float) $booking->bookingItems()
            ->where('is_waived', false)
            ->whereIn('inspection_status', ['damaged', 'missing'])
            ->sum('penalty_fee');

        $paidPenalties = (float) $booking->payments()
            ->where('type', 'penalty')
            ->sum('amount');

        return ($totalPenalties - $paidPenalties) > 0.001;
    }

    /**
     * Release collateral record with strict balance and penalty guards.
     *
     * @throws ValidationException
     */
    public function releaseCollateral(Booking $booking, ?User $actor = null, ?string $notes = null): CollateralRecord
    {
        $hasRemainingBalance = (float) $booking->remaining_balance > 0.00;
        $hasPenalties = $this->hasOutstandingPenalties($booking);

        if ($hasRemainingBalance && $hasPenalties) {
            throw ValidationException::withMessages([
                'collateral' => ['لا يمكن تسليم بطاقة الهوية قبل سداد الغرامة المستحقة والرصيد المتبقي'],
            ]);
        }

        if ($hasPenalties) {
            throw ValidationException::withMessages([
                'collateral' => ['لا يمكن تسليم بطاقة الهوية قبل سداد الغرامة المستحقة'],
            ]);
        }

        if ($hasRemainingBalance) {
            throw ValidationException::withMessages([
                'collateral' => ['لا يمكن تسليم بطاقة الهوية قبل سداد الرصيد المتبقي'],
            ]);
        }

        return DB::transaction(function () use ($booking, $actor, $notes) {
            $collateral = $booking->collateralRecord ?: CollateralRecord::firstOrCreate(
                ['booking_id' => $booking->id],
                [
                    'tenant_id' => $booking->tenant_id,
                    'status' => 'held',
                    'held_at' => now(),
                ]
            );

            $collateral->update([
                'status' => 'released',
                'released_at' => Carbon::now(),
                'released_by' => $actor?->id,
                'notes' => $notes ?? $collateral->notes,
            ]);

            // Transition booking to completed if it was in damage_pending and now all settled
            if ($booking->status === 'damage_pending' && ! $this->hasOutstandingPenalties($booking) && (float) $booking->remaining_balance == 0.00) {
                $booking->update(['status' => 'completed']);
            }

            return $collateral->fresh();
        });
    }

    /**
     * Waive a penalty for a specific damaged or missing booking item (Shop Owner only).
     *
     * @throws AuthorizationException
     */
    public function waivePenalty(BookingItem $bookingItem, User $actor, string $reason): void
    {
        if (! in_array($actor->role, ['owner', 'system_admin'], true)) {
            throw new AuthorizationException('فقط مالك المتجر يملك صلاحية إعفاء الغرامات.');
        }

        DB::transaction(function () use ($bookingItem, $actor, $reason) {
            $bookingItem->update([
                'is_waived' => true,
                'damage_notes' => trim(($bookingItem->damage_notes ?? '')." [إعفاء: {$reason} بواسطة {$actor->name}]"),
            ]);

            $booking = $bookingItem->booking;
            if ($booking && (float) $booking->remaining_balance == 0.00 && ! $this->hasOutstandingPenalties($booking)) {
                if ($booking->status === 'damage_pending') {
                    $booking->update(['status' => 'completed']);
                }
            }
        });
    }

    /**
     * Record a penalty payment for a booking.
     */
    public function payPenalty(Booking $booking, float $amount, string $paymentMethod, ?User $actor = null, ?string $referenceNumber = null): BookingPayment
    {
        return DB::transaction(function () use ($booking, $amount, $paymentMethod, $actor, $referenceNumber) {
            $payment = BookingPayment::create([
                'tenant_id' => $booking->tenant_id,
                'booking_id' => $booking->id,
                'amount' => $amount,
                'type' => 'penalty',
                'method' => $paymentMethod,
                'reference_number' => $referenceNumber,
                'recorded_by' => $actor?->id,
            ]);

            if (! $this->hasOutstandingPenalties($booking) && (float) $booking->remaining_balance == 0.00) {
                if ($booking->status === 'damage_pending') {
                    $booking->update(['status' => 'completed']);
                }
            }

            return $payment;
        });
    }

    /**
     * Record remaining balance settlement payment for a booking.
     */
    public function payRemainingBalance(Booking $booking, float $amount, string $paymentMethod, ?User $actor = null, ?string $referenceNumber = null): BookingPayment
    {
        return DB::transaction(function () use ($booking, $amount, $paymentMethod, $actor, $referenceNumber) {
            $payment = BookingPayment::create([
                'tenant_id' => $booking->tenant_id,
                'booking_id' => $booking->id,
                'amount' => $amount,
                'type' => 'final_payment',
                'method' => $paymentMethod,
                'reference_number' => $referenceNumber,
                'recorded_by' => $actor?->id,
            ]);

            $booking->remaining_balance = max(0.00, (float) $booking->remaining_balance - $amount);
            if ((float) $booking->remaining_balance == 0.00 && ! $this->hasOutstandingPenalties($booking)) {
                $booking->status = 'completed';
            }
            $booking->save();

            return $payment;
        });
    }
}
