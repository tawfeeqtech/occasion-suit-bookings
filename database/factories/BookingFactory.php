<?php

namespace Database\Factories;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pickupDate = fake()->dateTimeBetween('now', '+1 month');
        $returnDate = (clone $pickupDate)->modify('+3 days');
        $totalFee = fake()->randomFloat(2, 100, 500);
        $advancePaid = round($totalFee * 0.3, 2);

        return [
            'tenant_id' => null,
            'booking_number' => 'BK-'.date('Ym').'-'.fake()->unique()->numerify('####'),
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->phoneNumber(),
            'pickup_date' => $pickupDate->format('Y-m-d'),
            'event_date' => (clone $pickupDate)->modify('+1 day')->format('Y-m-d'),
            'return_date' => $returnDate->format('Y-m-d'),
            'status' => 'active',
            'total_fee' => $totalFee,
            'advance_paid' => $advancePaid,
            'remaining_balance' => $totalFee - $advancePaid,
            'payment_method' => 'cash',
            'alterations_notes' => null,
            'created_by' => null,
        ];
    }
}
