<?php

namespace Tests\Feature;

use App\Filament\Resources\Items\Pages\CreateItem;
use App\Filament\Resources\Items\Pages\ListItems;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentItemResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * AC-003.1: Inventory Item Creation with Custom Fields stored in JSONB.
     */
    public function test_can_create_item_with_dynamic_custom_fields(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant->id)->create();

        $this->actingAs($owner);
        TenantContext::setTenantId($tenant->id);

        Livewire::test(CreateItem::class)
            ->fillForm([
                'name' => 'بدلة كلاسيكية توكسيدو',
                'category' => 'suit',
                'size' => '48',
                'color' => 'Navy Blue',
                'status' => 'available',
                'rental_price' => 250.00,
                'custom_fields' => [
                    'lapel_style' => 'peak',
                    'fabric' => 'wool',
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('items', [
            'tenant_id' => $tenant->id,
            'name' => 'بدلة كلاسيكية توكسيدو',
            'category' => 'suit',
            'size' => '48',
            'color' => 'Navy Blue',
            'status' => 'available',
        ]);

        $createdItem = Item::where('name', 'بدلة كلاسيكية توكسيدو')->first();
        $this->assertNotNull($createdItem);
        $this->assertEquals([
            'lapel_style' => 'peak',
            'fabric' => 'wool',
        ], $createdItem->custom_fields);
    }

    /**
     * Owner can view items list page in Filament.
     */
    public function test_owner_can_view_items_list(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant->id)->create();

        $item = Item::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'بدلة عريس خاصة',
        ]);

        $this->actingAs($owner);
        TenantContext::setTenantId($tenant->id);

        Livewire::test(ListItems::class)
            ->assertCanSeeTableRecords([$item]);
    }
}
