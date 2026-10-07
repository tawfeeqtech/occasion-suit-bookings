<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Pilot Tenant
        $pilotTenant = Tenant::firstOrCreate(
            ['slug' => 'pilot-suit-shop'],
            [
                'name' => 'Al-Amir Suit Rentals (Pilot)',
                'slug' => 'pilot-suit-shop',
                'settings' => [
                    'buffer_hours' => 48,
                    'currency' => 'ILS',
                ],
                'is_active' => true,
            ]
        );

        // 2. System Admin (tenant_id = null)
        User::firstOrCreate(
            ['email' => 'admin@suitrent.com'],
            [
                'name' => 'System Admin',
                'email' => 'admin@suitrent.com',
                'password' => Hash::make('AdminSecret123!'),
                'role' => 'system_admin',
                'tenant_id' => null,
                'is_active' => true,
            ]
        );

        // 3. Pilot Shop Owner
        User::firstOrCreate(
            ['email' => 'owner@pilotshop.com'],
            [
                'name' => 'Ahmad Owner',
                'email' => 'owner@pilotshop.com',
                'password' => Hash::make('OwnerSecret123!'),
                'role' => 'owner',
                'tenant_id' => $pilotTenant->id,
                'telegram_user_id' => 111222333,
                'is_active' => true,
            ]
        );

        // 4. Pilot Shop Staff
        User::firstOrCreate(
            ['email' => 'staff@pilotshop.com'],
            [
                'name' => 'Khaled Staff',
                'email' => 'staff@pilotshop.com',
                'password' => Hash::make('StaffSecret123!'),
                'role' => 'staff',
                'tenant_id' => $pilotTenant->id,
                'telegram_user_id' => 444555666,
                'is_active' => true,
            ]
        );
    }
}
