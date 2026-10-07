<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Models\Item;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات العميل والمناسبة')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('customer_name')
                                    ->label('اسم العميل')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('customer_phone')
                                    ->label('رقم هاتف العميل')
                                    ->tel()
                                    ->required()
                                    ->maxLength(50),
                            ]),

                        Grid::make(3)
                            ->schema([
                                DatePicker::make('pickup_date')
                                    ->label('تاريخ الاستلام')
                                    ->required(),

                                DatePicker::make('event_date')
                                    ->label('تاريخ المناسبة'),

                                DatePicker::make('return_date')
                                    ->label('تاريخ الإرجاع')
                                    ->required(),
                            ]),
                    ]),

                Section::make('اختيار القطع والماليات')
                    ->schema([
                        Select::make('item_ids')
                            ->label('القطع المختارة للحجز')
                            ->multiple()
                            ->options(fn () => Item::where('status', '!=', 'retired')->pluck('name', 'id'))
                            ->required()
                            ->columnSpanFull(),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('total_fee')
                                    ->label('إجمالي الإيجار (₪)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->required()
                                    ->prefix('₪'),

                                TextInput::make('advance_paid')
                                    ->label('الدفعة المقدمة (₪)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->prefix('₪'),

                                Select::make('payment_method')
                                    ->label('طريقة الدفع')
                                    ->options([
                                        'cash' => 'نقداً (كاش)',
                                        'palpay' => 'PalPay (بال باي)',
                                        'jawwal_pay' => 'Jawwal Pay (جوال باي)',
                                        'bank_transfer' => 'تحويل بنكي',
                                    ])
                                    ->default('cash')
                                    ->required(),
                            ]),

                        Textarea::make('alterations_notes')
                            ->label('ملاحظات القياس والتعديل (Alterations)')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
