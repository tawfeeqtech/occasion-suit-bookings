<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'staff',
            'telegram_user_id' => null,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is a system admin.
     */
    public function systemAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'system_admin',
            'tenant_id' => null,
        ]);
    }

    /**
     * Indicate that the user is a shop owner.
     */
    public function owner(?string $tenantId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'owner',
            'tenant_id' => $tenantId ?? ($attributes['tenant_id'] ?? null),
        ]);
    }

    /**
     * Indicate that the user is staff.
     */
    public function staff(?string $tenantId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'staff',
            'tenant_id' => $tenantId ?? ($attributes['tenant_id'] ?? null),
        ]);
    }

    /**
     * Indicate that the user is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
