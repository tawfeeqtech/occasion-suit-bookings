<?php

namespace Database\Factories;

use App\Models\CollateralRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollateralRecord>
 */
class CollateralRecordFactory extends Factory
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
            'status' => 'held',
            'notes' => null,
            'held_at' => now(),
            'released_at' => null,
            'released_by' => null,
        ];
    }
}
