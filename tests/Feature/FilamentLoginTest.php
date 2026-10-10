<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_authenticate_via_filament_login(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->owner($tenant->id)->create([
            'email' => 'owner@pilotshop.com',
            'password' => Hash::make('OwnerSecret123!'),
            'is_active' => true,
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'owner@pilotshop.com',
                'password' => 'OwnerSecret123!',
            ])
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($user);
    }
}
