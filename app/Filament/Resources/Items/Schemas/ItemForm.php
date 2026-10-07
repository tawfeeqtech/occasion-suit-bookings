<?php

namespace App\Filament\Resources\Items\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('اسم القطعة / الموديل')
                    ->required()
                    ->maxLength(255),

                Select::make('category')
                    ->label('التصنيف')
                    ->options([
                        'suit' => 'بدلة كاملة (Suit)',
                        'shirt' => 'قميص (Shirt)',
                        'shoes' => 'حذاء (Shoes)',
                        'belt' => 'حزام (Belt)',
                        'tie' => 'كرافة / ربطة عنق (Tie)',
                        'vest' => 'صديري (Vest)',
                        'lapel_pin' => 'بروش / إكسسوار (Lapel Pin)',
                    ])
                    ->required(),

                TextInput::make('size')
                    ->label('المقاس')
                    ->required()
                    ->maxLength(50),

                TextInput::make('color')
                    ->label('اللون')
                    ->required()
                    ->maxLength(100),

                Select::make('status')
                    ->label('الحالة التشغيلية')
                    ->options([
                        'available' => 'متاح',
                        'booked' => 'محجوز',
                        'cleaning' => 'قيد التنظيف',
                        'maintenance' => 'قيد الصيانة',
                        'retired' => 'خارج الخدمة',
                    ])
                    ->default('available')
                    ->required(),

                TextInput::make('rental_price')
                    ->label('سعر الإيجار (₪)')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->prefix('₪'),

                KeyValue::make('custom_fields')
                    ->label('المواصفات والحقول المخصصة (JSONB)')
                    ->keyLabel('الخاصية (مثل: نوع القماش، القصة)')
                    ->valueLabel('القيمة (مثل: صوف إيطالي، سليم فيت)')
                    ->columnSpanFull(),
            ]);
    }
}
