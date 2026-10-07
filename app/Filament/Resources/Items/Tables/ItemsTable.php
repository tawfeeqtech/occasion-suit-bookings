<?php

namespace App\Filament\Resources\Items\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('اسم القطعة')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('category')
                    ->label('التصنيف')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'suit' => 'بدلة كاملة',
                        'shirt' => 'قميص',
                        'shoes' => 'حذاء',
                        'belt' => 'حزام',
                        'tie' => 'ربطة عنق',
                        'vest' => 'صديري',
                        'lapel_pin' => 'إكسسوار',
                        default => $state,
                    })
                    ->badge()
                    ->sortable(),

                TextColumn::make('size')
                    ->label('المقاس')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('color')
                    ->label('اللون')
                    ->searchable(),

                TextColumn::make('rental_price')
                    ->label('سعر الإيجار')
                    ->formatStateUsing(fn ($state): string => '₪ '.number_format((float) $state, 2))
                    ->sortable(),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->colors([
                        'success' => 'available',
                        'info' => 'booked',
                        'warning' => 'cleaning',
                        'danger' => 'maintenance',
                        'gray' => 'retired',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'available' => 'متاح',
                        'booked' => 'محجوز',
                        'cleaning' => 'قيد التنظيف',
                        'maintenance' => 'قيد الصيانة',
                        'retired' => 'خارج الخدمة',
                        default => $state,
                    })
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('التصنيف')
                    ->options([
                        'suit' => 'بدلة كاملة',
                        'shirt' => 'قميص',
                        'shoes' => 'حذاء',
                        'belt' => 'حزام',
                        'tie' => 'ربطة عنق',
                        'vest' => 'صديري',
                        'lapel_pin' => 'إكسسوار',
                    ]),

                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'available' => 'متاح',
                        'booked' => 'محجوز',
                        'cleaning' => 'قيد التنظيف',
                        'maintenance' => 'قيد الصيانة',
                        'retired' => 'خارج الخدمة',
                    ]),
            ])
            ->recordActions([
                EditAction::make()->label('تعديل'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('حذف المحدد'),
                ]),
            ]);
    }
}
