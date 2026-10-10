<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TelegramWhitelistAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * AC-002.2: Missing Telegram ID is rejected with 401.
     */
    public function test_missing_telegram_header_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/items');

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Unauthorized telegram user']);
    }

    /**
     * AC-002.2: Telegram user ID not registered is rejected with 401.
     */
    public function test_unauthorized_telegram_sender_rejected(): void
    {
        $response = $this->withHeader('X-Telegram-User-Id', '999888777')
            ->getJson('/api/v1/items');

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Unauthorized telegram user']);
    }

    /**
     * AC-002.5: Inactive staff Telegram user is rejected with 403.
     */
    public function test_deactivated_telegram_staff_is_forbidden(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->staff($tenant->id)->inactive()->create([
            'telegram_user_id' => 123456789,
        ]);

        $response = $this->withHeader('X-Telegram-User-Id', '123456789')
            ->getJson('/api/v1/items');

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Staff account is deactivated']);
    }

    /**
     * AC-002.3: Whitelisted Telegram staff is authenticated and scoped to tenant.
     */
    public function test_telegram_staff_request_uses_assigned_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $staffA = User::factory()->staff($tenantA->id)->create([
            'name' => 'Ahmad Staff',
            'telegram_user_id' => 555666777,
        ]);

        $itemA = Item::factory()->create([
            'tenant_id' => $tenantA->id,
            'name' => 'Tenant A Suit',
        ]);

        $tenantB = Tenant::factory()->create();
        $itemB = Item::factory()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Tenant B Suit',
        ]);

        $response = $this->withHeader('X-Telegram-User-Id', '555666777')
            ->getJson('/api/v1/items');

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Tenant A Suit']);
        $response->assertJsonMissing(['name' => 'Tenant B Suit']);
    }

    public function test_telegram_id_cannot_be_reused_across_shops(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        User::factory()->staff($tenantA->id)->create([
            'telegram_user_id' => 712345678,
        ]);

        try {
            DB::transaction(fn () => User::factory()->staff($tenantB->id)->create([
                'telegram_user_id' => 712345678,
            ]));
            $this->fail('A Telegram user ID must not be assignable to a second shop.');
        } catch (QueryException) {
            $this->assertSame(
                1,
                User::withoutGlobalScopes()->where('telegram_user_id', 712345678)->count()
            );
        }
    }
}
