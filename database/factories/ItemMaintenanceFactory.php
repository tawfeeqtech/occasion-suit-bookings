<?php

namespace Database\Factories;

use App\Models\ItemMaintenance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemMaintenance>
 */
class ItemMaintenanceFactory extends Factory
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
            'item_id' => null,
            'booking_id' => null,
            'status' => 'cleaning',
            'started_at' => now(),
            'expected_ready_at' => now()->addHours(48),
            'actual_ready_at' => null,
            'notes' => null,
        ];
    }

    /**
     * Indicate that the maintenance is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'actual_ready_at' => now(),
        ]);
    }
}
