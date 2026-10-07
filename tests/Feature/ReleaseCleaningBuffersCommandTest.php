<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemMaintenance;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseCleaningBuffersCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * AC-005.3: Automated buffer expiry execution via buffer:release-clean-items command.
     */
    public function test_expired_buffer_items_auto_release_to_available(): void
    {
        $tenant = Tenant::factory()->create();
        TenantContext::setTenantId($tenant->id);

        $now = Carbon::now();

        // Create 3 items in cleaning with past expected_ready_at
        $item1 = Item::factory()->create(['tenant_id' => $tenant->id, 'status' => 'cleaning']);
        $item2 = Item::factory()->create(['tenant_id' => $tenant->id, 'status' => 'cleaning']);
        $item3 = Item::factory()->create(['tenant_id' => $tenant->id, 'status' => 'cleaning']);

        $m1 = ItemMaintenance::factory()->create([
            'tenant_id' => $tenant->id,
            'item_id' => $item1->id,
            'status' => 'cleaning',
            'expected_ready_at' => $now->copy()->subHours(1),
        ]);

        $m2 = ItemMaintenance::factory()->create([
            'tenant_id' => $tenant->id,
            'item_id' => $item2->id,
            'status' => 'cleaning',
            'expected_ready_at' => $now->copy()->subHours(2),
        ]);

        $m3 = ItemMaintenance::factory()->create([
            'tenant_id' => $tenant->id,
            'item_id' => $item3->id,
            'status' => 'cleaning',
            'expected_ready_at' => $now->copy()->subMinutes(10),
        ]);

        TenantContext::clear();

        // Run the Artisan command
        $this->artisan('buffer:release-clean-items')
            ->expectsOutputToContain('Released 3 items from cleaning buffer to available status.')
            ->assertExitCode(0);

        // Verify items are now available
        $this->assertEquals('available', $item1->fresh()->status);
        $this->assertEquals('available', $item2->fresh()->status);
        $this->assertEquals('available', $item3->fresh()->status);

        // Verify maintenance records are marked completed
        $this->assertEquals('completed', $m1->fresh()->status);
        $this->assertNotNull($m1->fresh()->actual_ready_at);

        $this->assertEquals('completed', $m2->fresh()->status);
        $this->assertNotNull($m2->fresh()->actual_ready_at);

        $this->assertEquals('completed', $m3->fresh()->status);
        $this->assertNotNull($m3->fresh()->actual_ready_at);
    }

    /**
     * Non-expired cleaning items remain in cleaning buffer.
     */
    public function test_non_expired_items_remain_in_cleaning(): void
    {
        $tenant = Tenant::factory()->create();
        TenantContext::setTenantId($tenant->id);

        $now = Carbon::now();

        $item = Item::factory()->create(['tenant_id' => $tenant->id, 'status' => 'cleaning']);

        $maintenance = ItemMaintenance::factory()->create([
            'tenant_id' => $tenant->id,
            'item_id' => $item->id,
            'status' => 'cleaning',
            'expected_ready_at' => $now->copy()->addHours(24),
        ]);

        TenantContext::clear();

        $this->artisan('buffer:release-clean-items')
            ->expectsOutputToContain('No cleaning items due for release.')
            ->assertExitCode(0);

        $this->assertEquals('cleaning', $item->fresh()->status);
        $this->assertEquals('cleaning', $maintenance->fresh()->status);
        $this->assertNull($maintenance->fresh()->actual_ready_at);
    }
}
