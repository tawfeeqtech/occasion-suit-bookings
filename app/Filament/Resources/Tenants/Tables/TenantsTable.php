<?php

namespace App\Filament\Resources\Tenants\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TenantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('اسم المتجر')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('slug')
                    ->label('المعرف (Slug)')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('settings.buffer_hours')
                    ->label('فترة التنظيف')
                    ->formatStateUsing(fn ($state) => ($state ?? 48).' ساعة')
                    ->sortable(),

                TextColumn::make('settings.currency')
                    ->label('العملة')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'ILS' => 'شيكل (₪)',
                        'JOD' => 'دينار (JOD)',
                        'USD' => 'دولار ($)',
                        default => $state ?? 'ILS',
                    }),

                TextColumn::make('is_active')
                    ->label('حالة الاشتراك')
                    ->badge()
                    ->colors([
                        'success' => true,
                        'danger' => false,
                    ])
                    ->formatStateUsing(fn (bool $state): string => $state ? 'نشط' : 'معطل')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()->label('تعديل الإعدادات'),
            ]);
    }
}
