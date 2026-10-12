<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemApiCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_staff_can_create_single_item_and_receives_complete_resource_with_smart_defaults(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->staff($tenant->id)->create([
            'telegram_user_id' => 431234567,
        ]);

        $response = $this->withHeader('X-Telegram-User-Id', '431234567')
            ->postJson('/api/v1/items', [
                'name' => 'بدلة زفاف بيضاء',
            ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'category',
                    'size',
                    'color',
                    'status',
                    'rental_price',
                    'custom_fields',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJsonPath('data.name', 'بدلة زفاف بيضاء')
            ->assertJsonPath('data.category', 'عام')
            ->assertJsonPath('data.size', 'غير محدد')
            ->assertJsonPath('data.color', 'غير محدد')
            ->assertJsonPath('data.status', 'available')
            ->assertJsonPath('data.rental_price', 0)
            ->assertJsonPath('data.custom_fields', null);

        $this->assertDatabaseHas('items', [
            'tenant_id' => $tenant->id,
            'name' => 'بدلة زفاف بيضاء',
            'category' => 'عام',
            'size' => 'غير محدد',
            'color' => 'غير محدد',
            'status' => 'available',
            'rental_price' => '0.00',
        ]);
        $this->assertDatabaseCount('items', 1);
    }

    public function test_owner_can_create_item_with_arabic_details_and_custom_json_fields(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->owner($tenant->id)->create([
            'telegram_user_id' => 531234567,
        ]);
        $customFields = [
            'item_code' => 'SUIT-NVY-50',
            'deposit' => 75,
            'notes' => 'تفاصيل خاصة بالمحل',
        ];

        $response = $this->withHeader('X-Telegram-User-Id', '531234567')
            ->postJson('/api/v1/items', [
                'name' => 'بدلة كحلي ملكي',
                'category' => 'بدلة',
                'size' => '٥٠',
                'color' => 'كحلي',
                'rental_price' => 250,
                'status' => 'maintenance',
                'custom_fields' => $customFields,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'بدلة كحلي ملكي')
            ->assertJsonPath('data.category', 'بدلة')
            ->assertJsonPath('data.size', '٥٠')
            ->assertJsonPath('data.color', 'كحلي')
            ->assertJsonPath('data.status', 'maintenance')
            ->assertJsonPath('data.rental_price', 250)
            ->assertJsonPath('data.custom_fields', $customFields);

        $this->assertDatabaseHas('items', [
            'tenant_id' => $tenant->id,
            'name' => 'بدلة كحلي ملكي',
            'category' => 'بدلة',
            'size' => '٥٠',
            'color' => 'كحلي',
            'status' => 'maintenance',
            'rental_price' => '250.00',
        ]);

        $persistedCustomFields = Item::query()->firstOrFail()->custom_fields;

        $this->assertSame('SUIT-NVY-50', $persistedCustomFields['item_code']);
        $this->assertSame(75, $persistedCustomFields['deposit']);
        $this->assertSame('تفاصيل خاصة بالمحل', $persistedCustomFields['notes']);
    }

    public function test_staff_can_authenticate_with_telegram_user_id_in_request_body(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->staff($tenant->id)->create([
            'telegram_user_id' => 581234567,
        ]);

        $response = $this->postJson('/api/v1/items', [
            'telegram_user_id' => 581234567,
            'name' => 'قطعة عبر مصادقة جسم الطلب',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'قطعة عبر مصادقة جسم الطلب');

        $this->assertDatabaseHas('items', [
            'tenant_id' => $tenant->id,
            'name' => 'قطعة عبر مصادقة جسم الطلب',
        ]);
    }

    public function test_authenticated_tenant_is_used_and_tenant_id_payload_is_ignored(): void
    {
        $tenantA = Tenant::factory()->create();
        User::factory()->staff($tenantA->id)->create([
            'telegram_user_id' => 631234567,
        ]);
        $tenantB = Tenant::factory()->create();

        $response = $this->withHeader('X-Telegram-User-Id', '631234567')
            ->postJson('/api/v1/items', [
                'name' => 'مخزون المتجر الأول',
                'tenant_id' => $tenantB->id,
            ]);

        $response->assertCreated();
        $createdItemId = $response->json('data.id');

        $this->assertDatabaseHas('items', [
            'id' => $createdItemId,
            'tenant_id' => $tenantA->id,
            'name' => 'مخزون المتجر الأول',
        ]);
        $this->assertDatabaseMissing('items', [
            'id' => $createdItemId,
            'tenant_id' => $tenantB->id,
        ]);

        TenantContext::setTenantId($tenantB->id);
        $this->assertFalse(Item::query()->whereKey($createdItemId)->exists());
    }

    public function test_missing_name_returns_422_without_creating_item_or_audit_log(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->staff($tenant->id)->create([
            'telegram_user_id' => 731234567,
        ]);

        $response = $this->withHeader('X-Telegram-User-Id', '731234567')
            ->postJson('/api/v1/items', []);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'The name field is required.')
            ->assertJsonPath('errors.name.0', 'The name field is required.');

        $this->assertDatabaseCount('items', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_empty_name_returns_422_without_persisting_item(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->staff($tenant->id)->create([
            'telegram_user_id' => 831234567,
        ]);

        $response = $this->withHeader('X-Telegram-User-Id', '831234567')
            ->postJson('/api/v1/items', [
                'name' => '',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'The name field is required.');

        $this->assertDatabaseCount('items', 0);
    }

    public function test_invalid_item_attributes_return_422_without_persisting_item(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->staff($tenant->id)->create([
            'telegram_user_id' => 931234567,
        ]);

        $response = $this->withHeader('X-Telegram-User-Id', '931234567')
            ->postJson('/api/v1/items', [
                'name' => str_repeat('ع', 256),
                'category' => str_repeat('ع', 101),
                'size' => str_repeat('٥', 51),
                'color' => str_repeat('ك', 51),
                'rental_price' => -1,
                'status' => 'retired',
                'custom_fields' => 'not-an-object',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'category',
                'size',
                'color',
                'rental_price',
                'status',
                'custom_fields',
            ]);

        $this->assertDatabaseCount('items', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_batch_payload_is_rejected_without_creating_items(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->staff($tenant->id)->create([
            'telegram_user_id' => 981234567,
        ]);

        $response = $this->withHeader('X-Telegram-User-Id', '981234567')
            ->postJson('/api/v1/items', [
                ['name' => 'القطعة الأولى'],
                ['name' => 'القطعة الثانية'],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('items', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_missing_or_unregistered_telegram_user_receives_401(): void
    {
        $missingHeaderResponse = $this->postJson('/api/v1/items', [
            'name' => 'بدلة غير مصرح بها',
        ]);
        $missingHeaderResponse->assertUnauthorized()
            ->assertJsonPath('error', 'Unauthorized telegram user');

        $unknownUserResponse = $this->withHeader('X-Telegram-User-Id', '999123456')
            ->postJson('/api/v1/items', [
                'name' => 'بدلة غير مصرح بها',
            ]);
        $unknownUserResponse->assertUnauthorized()
            ->assertJsonPath('error', 'Unauthorized telegram user');

        $this->assertDatabaseCount('items', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_deactivated_telegram_user_receives_403_without_persisting_item(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->staff($tenant->id)->inactive()->create([
            'telegram_user_id' => 1031234567,
        ]);

        $response = $this->withHeader('X-Telegram-User-Id', '1031234567')
            ->postJson('/api/v1/items', [
                'name' => 'بدلة لموظف معطل',
            ]);

        $response->assertForbidden()
            ->assertJsonPath('error', 'Staff account is deactivated');

        $this->assertDatabaseCount('items', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_system_admin_cannot_create_tenant_inventory_through_staff_endpoint(): void
    {
        User::factory()->systemAdmin()->create([
            'telegram_user_id' => 1091234567,
        ]);

        $response = $this->withHeader('X-Telegram-User-Id', '1091234567')
            ->postJson('/api/v1/items', [
                'name' => 'قطعة خارج نطاق المستأجر',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseCount('items', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_item_creation_audit_log_records_actor_tenant_and_item_id(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->staff($tenant->id)->create([
            'telegram_user_id' => 1131234567,
        ]);

        $response = $this->withHeader('X-Telegram-User-Id', '1131234567')
            ->postJson('/api/v1/items', [
                'name' => 'بدلة موثقة',
            ]);

        $response->assertCreated();
        $itemId = $response->json('data.id');
        $auditLog = AuditLog::withoutGlobalScopes()
            ->where('action', 'item_created')
            ->where('entity_id', $itemId)
            ->firstOrFail();

        $this->assertSame($tenant->id, $auditLog->tenant_id);
        $this->assertSame('user', $auditLog->actor_type);
        $this->assertSame($staff->id, $auditLog->actor_id);
        $this->assertSame('Item', $auditLog->entity_type);
        $this->assertSame($itemId, $auditLog->entity_id);
    }
}
