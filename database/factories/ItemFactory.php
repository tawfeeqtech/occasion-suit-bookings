<?php

namespace Database\Factories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
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
            'name' => fake()->words(3, true).' Suit',
            'category' => fake()->randomElement(['tuxedo', 'classic_suit', 'blazer', 'traditional']),
            'size' => fake()->randomElement(['48', '50', '52', '54', '56']),
            'color' => fake()->randomElement(['Black', 'Navy', 'Charcoal', 'Burgundy', 'Royal Blue']),
            'status' => 'available',
            'rental_price' => fake()->randomFloat(2, 50, 400),
            'custom_fields' => null,
        ];
    }

    /**
     * Indicate that the item is booked.
     */
    public function booked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'booked',
        ]);
    }

    /**
     * Indicate that the item is out with a customer.
     */
    public function outWithCustomer(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'out_with_customer',
        ]);
    }

    /**
     * Indicate that the item is in cleaning.
     */
    public function cleaning(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cleaning',
        ]);
    }

    /**
     * Indicate that the item is in maintenance.
     */
    public function maintenance(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'maintenance',
        ]);
    }
}
