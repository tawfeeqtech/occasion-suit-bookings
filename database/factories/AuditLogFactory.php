<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'actor_type' => 'user',
            'actor_id' => fake()->uuid(),
            'action' => 'booking.created',
            'entity_type' => 'Booking',
            'entity_id' => fake()->uuid(),
            'metadata' => [
                'note' => fake()->sentence(),
            ],
            'ip_address' => fake()->ipv4(),
            'created_at' => now(),
        ];
    }
}
