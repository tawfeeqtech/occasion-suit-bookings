<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingPayment;
use App\Models\CollateralRecord;
use App\Models\Item;
use App\Models\ItemMaintenance;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
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
                'name' => 'متجر الأمير لبدلات المناسبات (Pilot)',
                'slug' => 'pilot-suit-shop',
                'settings' => [
                    'buffer_hours' => 48,
                    'currency' => 'ILS',
                ],
                'is_active' => true,
            ]
        );

        // Set tenant context for model creation
        TenantContext::setTenantId($pilotTenant->id);

        // 2. System Admin (tenant_id = null)
        User::withoutGlobalScopes()->firstOrCreate(
            ['email' => 'admin@suitrent.com'],
            [
                'name' => 'مسؤول النظام (System Admin)',
                'email' => 'admin@suitrent.com',
                'password' => Hash::make('AdminSecret123!'),
                'role' => 'system_admin',
                'tenant_id' => null,
                'is_active' => true,
            ]
        );

        // 3. Pilot Shop Owner
        $owner = User::withoutGlobalScopes()->firstOrCreate(
            ['email' => 'owner@pilotshop.com'],
            [
                'name' => 'أحمد صاحب المتجر',
                'email' => 'owner@pilotshop.com',
                'password' => Hash::make('OwnerSecret123!'),
                'role' => 'owner',
                'tenant_id' => $pilotTenant->id,
                'telegram_user_id' => 111222333,
                'is_active' => true,
            ]
        );

        // 4. Pilot Shop Staff
        $staff = User::withoutGlobalScopes()->firstOrCreate(
            ['email' => 'staff@pilotshop.com'],
            [
                'name' => 'خالد موظف الفرع',
                'email' => 'staff@pilotshop.com',
                'password' => Hash::make('StaffSecret123!'),
                'role' => 'staff',
                'tenant_id' => $pilotTenant->id,
                'telegram_user_id' => 444555666,
                'is_active' => true,
            ]
        );

        // 5. Inventory Items (Suits & Accessories)
        $itemsData = [
            'SUIT-NAVY-50' => [
                'name' => 'بدلة كلاسيك كحلي فاخرة 3 قطع (مقاس 50)',
                'category' => 'suit',
                'size' => '50',
                'color' => 'كحلي',
                'rental_price' => 350.00,
                'status' => 'available',
                'custom_fields' => ['item_code' => 'SUIT-NAVY-50', 'deposit' => 100.00, 'fabric' => 'صوف إيطالي 100%', 'pieces' => 'جاكيت + بنطال + صديري', 'fit' => 'Slim Fit'],
            ],
            'TUX-BLK-52' => [
                'name' => 'توكسيدو ملكي أسود شال ساتان (مقاس 52)',
                'category' => 'tuxedo',
                'size' => '52',
                'color' => 'أسود',
                'rental_price' => 450.00,
                'status' => 'booked',
                'custom_fields' => ['item_code' => 'TUX-BLK-52', 'deposit' => 150.00, 'lapel' => 'ساتان حرير', 'edition' => 'Royal Groom 2026'],
            ],
            'SUIT-GRY-48' => [
                'name' => 'بدلة رمادي فاتح صيفي سليم فت (مقاس 48)',
                'category' => 'suit',
                'size' => '48',
                'color' => 'رمادي فاتح',
                'rental_price' => 300.00,
                'status' => 'out_with_customer',
                'custom_fields' => ['item_code' => 'SUIT-GRY-48', 'deposit' => 100.00, 'fabric' => 'كتان وصوف تركي', 'season' => 'صيف 2026'],
            ],
            'BLZ-BUR-50' => [
                'name' => 'بليزر مخمل عودي للمناسبات (مقاس 50)',
                'category' => 'blazer',
                'size' => '50',
                'color' => 'عودي / عنابي',
                'rental_price' => 200.00,
                'status' => 'cleaning',
                'custom_fields' => ['item_code' => 'BLZ-BUR-50', 'deposit' => 50.00, 'material' => 'مخمل ملكي ناعم'],
            ],
            'SUIT-BLK-54' => [
                'name' => 'بدلة سوداء رسمية كلاسيكية (مقاس 54)',
                'category' => 'suit',
                'size' => '54',
                'color' => 'أسود',
                'rental_price' => 280.00,
                'status' => 'available',
                'custom_fields' => ['item_code' => 'SUIT-BLK-54', 'deposit' => 100.00, 'style' => 'Standard Fit'],
            ],
            'SHOE-OXF-42' => [
                'name' => 'حذاء أكسفورد أسود جلد طبيعي (مقاس 42)',
                'category' => 'shoes',
                'size' => '42',
                'color' => 'أسود لامع',
                'rental_price' => 70.00,
                'status' => 'available',
                'custom_fields' => ['item_code' => 'SHOE-OXF-42', 'deposit' => 30.00, 'leather' => 'جلد عجل طبيعي'],
            ],
            'ACC-CUFF-GLD' => [
                'name' => 'طقم أزرار أكمام وساعة كبك ذهبي',
                'category' => 'accessory',
                'size' => 'Standard',
                'color' => 'ذهبي ملكي',
                'rental_price' => 40.00,
                'status' => 'available',
                'custom_fields' => ['item_code' => 'ACC-CUFF-GLD', 'deposit' => 20.00, 'box' => 'علبة مخمل خضراء'],
            ],
            'ACC-TIE-BUR' => [
                'name' => 'ربطة عنق ساتان عنابي ومنديل جيب',
                'category' => 'accessory',
                'size' => 'Standard',
                'color' => 'عنابي',
                'rental_price' => 25.00,
                'status' => 'available',
                'custom_fields' => ['item_code' => 'ACC-TIE-BUR', 'deposit' => 10.00, 'finish' => 'ساتان حرير'],
            ],
        ];

        $createdItems = [];
        foreach ($itemsData as $key => $itemData) {
            $createdItems[$key] = Item::firstOrCreate(
                ['name' => $itemData['name'], 'tenant_id' => $pilotTenant->id],
                $itemData
            );
        }

        $today = Carbon::today()->format('Y-m-d');
        $afterTomorrow = Carbon::today()->addDays(2)->format('Y-m-d');
        $yesterday = Carbon::yesterday()->format('Y-m-d');

        // 6. Booking 1: Today Pickup (العريس طارق منصور)
        $booking1 = Booking::firstOrCreate(
            ['customer_phone' => '0599112233', 'tenant_id' => $pilotTenant->id],
            [
                'booking_number' => 'BK-'.date('Ymd').'-001',
                'created_by' => $staff->id,
                'customer_name' => 'طارق منصور (عريس)',
                'pickup_date' => $today,
                'return_date' => $afterTomorrow,
                'total_fee' => 450.00,
                'advance_paid' => 200.00,
                'remaining_balance' => 250.00,
                'payment_method' => 'cash',
                'status' => 'active',
                'alterations_notes' => 'تقصير البنطال 2 سم - جاهز للاستلام الفوري',
            ]
        );

        BookingItem::firstOrCreate([
            'tenant_id' => $pilotTenant->id,
            'booking_id' => $booking1->id,
            'item_id' => $createdItems['TUX-BLK-52']->id,
        ], [
            'rental_price' => 450.00,
            'inspection_status' => 'clean_pass',
            'penalty_fee' => 0.00,
        ]);

        BookingPayment::firstOrCreate([
            'tenant_id' => $pilotTenant->id,
            'booking_id' => $booking1->id,
            'type' => 'advance',
        ], [
            'amount' => 200.00,
            'method' => 'cash',
            'recorded_by' => $staff->id,
        ]);

        CollateralRecord::firstOrCreate([
            'tenant_id' => $pilotTenant->id,
            'booking_id' => $booking1->id,
        ], [
            'status' => 'held',
            'held_at' => now(),
            'notes' => 'هوية وطنية رقم 908123456 باسم طارق منصور - في الخزنة',
        ]);

        // 7. Booking 2: Today Return (الزبون يوسف كمال)
        $booking2 = Booking::firstOrCreate(
            ['customer_phone' => '0599445566', 'tenant_id' => $pilotTenant->id],
            [
                'booking_number' => 'BK-'.date('Ymd').'-002',
                'created_by' => $staff->id,
                'customer_name' => 'يوسف كمال',
                'pickup_date' => $yesterday,
                'return_date' => $today,
                'total_fee' => 300.00,
                'advance_paid' => 300.00,
                'remaining_balance' => 0.00,
                'payment_method' => 'palpay',
                'status' => 'active',
                'alterations_notes' => 'مناسبة تخرج - بدون تعديلات',
            ]
        );

        BookingItem::firstOrCreate([
            'tenant_id' => $pilotTenant->id,
            'booking_id' => $booking2->id,
            'item_id' => $createdItems['SUIT-GRY-48']->id,
        ], [
            'rental_price' => 300.00,
            'inspection_status' => 'clean_pass',
            'penalty_fee' => 0.00,
        ]);

        BookingPayment::firstOrCreate([
            'tenant_id' => $pilotTenant->id,
            'booking_id' => $booking2->id,
            'type' => 'final_payment',
        ], [
            'amount' => 300.00,
            'method' => 'palpay',
            'recorded_by' => $staff->id,
        ]);

        CollateralRecord::firstOrCreate([
            'tenant_id' => $pilotTenant->id,
            'booking_id' => $booking2->id,
        ], [
            'status' => 'held',
            'held_at' => now()->subDay(),
            'notes' => 'هوية وطنية رقم 402987654 باسم يوسف كمال - مطلوب الفحص وفك الحجز',
        ]);

        // 8. Maintenance / Cleaning Item
        $cleaningItem = $createdItems['BLZ-BUR-50'];
        ItemMaintenance::firstOrCreate(
            ['item_id' => $cleaningItem->id, 'tenant_id' => $pilotTenant->id],
            [
                'status' => 'cleaning',
                'started_at' => now()->subHours(12),
                'expected_ready_at' => now()->addHours(36),
                'notes' => 'دورة تنظيف وتعقيم وبخار دورية بعد إرجاع سليم',
            ]
        );

        // 9. Initial Audit Logs
        AuditLog::firstOrCreate(
            [
                'tenant_id' => $pilotTenant->id,
                'action' => 'booking_created',
                'entity_type' => Booking::class,
                'entity_id' => $booking1->id,
            ],
            [
                'actor_type' => 'user',
                'actor_id' => (string) $staff->id,
                'metadata' => [
                    'customer_name' => $booking1->customer_name,
                    'total_fee' => $booking1->total_fee,
                    'pickup_date' => $booking1->pickup_date,
                ],
                'ip_address' => '127.0.0.1',
            ]
        );

        TenantContext::clear();
    }
}
