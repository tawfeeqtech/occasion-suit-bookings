<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_number' => $this->booking_number,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'pickup_date' => $this->pickup_date?->format('Y-m-d'),
            'event_date' => $this->event_date?->format('Y-m-d'),
            'return_date' => $this->return_date?->format('Y-m-d'),
            'status' => $this->status,
            'total_fee' => (float) $this->total_fee,
            'advance_paid' => (float) $this->advance_paid,
            'remaining_balance' => (float) $this->remaining_balance,
            'payment_method' => $this->payment_method,
            'alterations_notes' => $this->alterations_notes,
            'collateral_status' => $this->collateralRecord?->status ?? 'held',
            'items' => $this->bookingItems->map(fn ($bi) => [
                'id' => $bi->item_id,
                'name' => $bi->item?->name,
                'rental_price' => (float) $bi->rental_price,
                'inspection_status' => $bi->inspection_status,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
