<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * Test retrieving items listing filtered by tenant.
     */
    public function test_get_items_scoped_to_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $staffA = User::factory()->staff($tenantA->id)->create([
            'telegram_user_id' => 123123123,
        ]);

        $itemA1 = Item::factory()->create(['tenant_id' => $tenantA->id, 'name' => 'بدلة سموكنج سوداء']);
        $itemA2 = Item::factory()->create(['tenant_id' => $tenantA->id, 'name' => 'بدلة كلاسيك كحلية']);

        $tenantB = Tenant::factory()->create();
        $itemB = Item::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'بدلة متجر آخر']);

        $response = $this->withHeader('X-Telegram-User-Id', '123123123')
            ->getJson('/api/v1/items');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment(['name' => 'بدلة سموكنج سوداء']);
        $response->assertJsonFragment(['name' => 'بدلة كلاسيك كحلية']);
        $response->assertJsonMissing(['name' => 'بدلة متجر آخر']);
    }

    /**
     * Test search filter by name or color.
     */
    public function test_filter_items_by_search_keyword(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->staff($tenant->id)->create([
            'telegram_user_id' => 123123123,
        ]);

        Item::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'بدلة توكسيدو مخمل',
            'color' => 'Black',
        ]);

        Item::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'بدلة رسمية سادة',
            'color' => 'Navy',
        ]);

        $response = $this->withHeader('X-Telegram-User-Id', '123123123')
            ->getJson('/api/v1/items?search=مخمل');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['name' => 'بدلة توكسيدو مخمل']);
        $response->assertJsonMissing(['name' => 'بدلة رسمية سادة']);
    }
}
