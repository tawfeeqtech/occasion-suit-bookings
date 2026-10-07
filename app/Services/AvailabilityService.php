<?php

namespace App\Services;

use App\Models\BookingItem;
use App\Models\Item;
use App\Models\ItemMaintenance;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Carbon\Carbon;

class AvailabilityService
{
    /**
     * Check availability for a batch of items over a date range.
     *
     * @param  list<string>  $itemIds
     * @return array{
     *     available: bool,
     *     items: list<array{
     *         id: string,
     *         name?: string,
     *         status: string,
     *         is_available: bool,
     *         conflict: string|null,
     *         expected_ready_at: string|null
     *     }>
     * }
     */
    public function checkAvailability(
        array $itemIds,
        Carbon|string $pickupDate,
        Carbon|string $returnDate,
        ?string $tenantId = null
    ): array {
        $pickup = $pickupDate instanceof Carbon ? $pickupDate->copy()->startOfDay() : Carbon::parse($pickupDate)->startOfDay();
        $return = $returnDate instanceof Carbon ? $returnDate->copy()->endOfDay() : Carbon::parse($returnDate)->endOfDay();

        $resolvedTenantId = $tenantId ?? TenantContext::getTenantId();

        // 1. Resolve tenant buffer hours (default 48 hours)
        $bufferHours = 48;
        if ($resolvedTenantId) {
            $tenant = Tenant::find($resolvedTenantId);
            if ($tenant && isset($tenant->settings['buffer_hours'])) {
                $bufferHours = (int) $tenant->settings['buffer_hours'];
            }
        }

        // 2. Fetch all requested items
        $itemsQuery = Item::whereIn('id', $itemIds);
        if ($resolvedTenantId) {
            $itemsQuery->where('tenant_id', $resolvedTenantId);
        }
        $items = $itemsQuery->get()->keyBy('id');

        $overallAvailable = true;
        $itemsResult = [];

        foreach ($itemIds as $itemId) {
            $item = $items->get($itemId);

            if (! $item) {
                $overallAvailable = false;
                $itemsResult[] = [
                    'id' => $itemId,
                    'status' => 'not_found',
                    'is_available' => false,
                    'conflict' => 'Item does not exist or does not belong to this tenant.',
                    'expected_ready_at' => null,
                ];

                continue;
            }

            // Check 1: Is item currently in maintenance / cleaning?
            $activeMaintenance = ItemMaintenance::where('item_id', $itemId)
                ->whereIn('status', ['cleaning', 'maintenance'])
                ->where('expected_ready_at', '>', $pickup)
                ->latest('expected_ready_at')
                ->first();

            if ($activeMaintenance) {
                $overallAvailable = false;
                $itemsResult[] = [
                    'id' => $itemId,
                    'name' => $item->name,
                    'status' => $activeMaintenance->status,
                    'is_available' => false,
                    'conflict' => sprintf('العنصر قيد %s حتى %s', $activeMaintenance->status === 'cleaning' ? 'التنظيف والتعقيم' : 'الصيانة', $activeMaintenance->expected_ready_at->toDateTimeString()),
                    'expected_ready_at' => $activeMaintenance->expected_ready_at->toIso8601String(),
                ];

                continue;
            }

            // Check 2: Check overlapping active bookings
            // Booking conflicts if:
            // Existing booking's (pickup_date <= requested return_date) AND
            // (existing return_date + buffer_hours >= requested pickup_date)
            // AND (requested return_date + buffer_hours >= existing pickup_date)
            $conflictingBookingItem = BookingItem::where('item_id', $itemId)
                ->whereHas('booking', function ($query) use ($pickup, $return, $bufferHours) {
                    $query->whereIn('status', ['active', 'overdue', 'damage_pending'])
                        ->where(function ($q) use ($pickup, $return, $bufferHours) {
                            $q->whereDate('pickup_date', '<=', $return->toDateString())
                                ->whereRaw("return_date + (? || ' hours')::interval >= ?", [$bufferHours, $pickup->toDateTimeString()]);
                        });
                })
                ->with('booking')
                ->first();

            if ($conflictingBookingItem && $conflictingBookingItem->booking) {
                $conflictBooking = $conflictingBookingItem->booking;
                $bufferEnd = Carbon::parse($conflictBooking->return_date)->endOfDay()->addHours($bufferHours);

                $overallAvailable = false;
                $itemsResult[] = [
                    'id' => $itemId,
                    'name' => $item->name,
                    'status' => 'booked',
                    'is_available' => false,
                    'conflict' => sprintf('العنصر محجوز في الفترة (%s إلى %s) وفترة التنظيف حتى %s', $conflictBooking->pickup_date->toDateString(), $conflictBooking->return_date->toDateString(), $bufferEnd->toDateTimeString()),
                    'expected_ready_at' => $bufferEnd->toIso8601String(),
                ];

                continue;
            }

            // Item is available!
            $itemsResult[] = [
                'id' => $itemId,
                'name' => $item->name,
                'status' => 'available',
                'is_available' => true,
                'conflict' => null,
                'expected_ready_at' => null,
            ];
        }

        return [
            'available' => $overallAvailable,
            'items' => $itemsResult,
        ];
    }
}
