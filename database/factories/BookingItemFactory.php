<?php

namespace Database\Factories;

use App\Models\BookingItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingItem>
 */
class BookingItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'booking_id' => null,
            'item_id' => null,
            'rental_price' => fake()->randomFloat(2, 50, 300),
            'inspection_status' => 'clean_pass',
            'penalty_fee' => 0.00,
            'penalty_reason' => null,
            'is_waived' => false,
            'damage_notes' => null,
        ];
    }
}
