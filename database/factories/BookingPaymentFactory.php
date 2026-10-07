<?php

namespace Database\Factories;

use App\Models\BookingPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingPayment>
 */
class BookingPaymentFactory extends Factory
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
            'amount' => fake()->randomFloat(2, 50, 200),
            'type' => 'advance',
            'method' => 'cash',
            'reference_number' => null,
            'recorded_by' => null,
        ];
    }
}
