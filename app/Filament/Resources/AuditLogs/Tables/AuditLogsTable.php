<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('الوقت والتاريخ')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),

                TextColumn::make('action')
                    ->label('العملية / الحدث')
                    ->badge()
                    ->colors([
                        'success' => 'booking.created',
                        'warning' => 'return.processed',
                        'info' => 'collateral.released',
                        'danger' => 'penalty.waived',
                        'primary' => 'penalty.paid',
                        'gray' => 'buffer.released',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'booking.created' => 'إنشاء حجز',
                        'return.processed' => 'معالجة إرجاع',
                        'collateral.released' => 'تسليم هوية',
                        'penalty.waived' => 'إعفاء غرامة',
                        'penalty.paid' => 'سداد غرامة',
                        'buffer.released' => 'انتهاء تنظيف',
                        default => $state,
                    })
                    ->sortable(),

                TextColumn::make('actor_type')
                    ->label('نوع الفاعل')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'user' => 'موظف',
                        'system' => 'النظام الآلي',
                        default => $state,
                    }),

                TextColumn::make('actorUser.name')
                    ->label('اسم الفاعل')
                    ->default(fn ($record) => $record->actor_type === 'system' ? 'مجدول النظام (Scheduler)' : $record->actor_id),

                TextColumn::make('entity_type')
                    ->label('نوع الكيان')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'Booking' => 'حجز',
                        'BookingItem' => 'قطعة حجز',
                        'CollateralRecord' => 'سجل ضمان',
                        'ItemMaintenance' => 'صيانة وتنظيف',
                        'BookingPayment' => 'دفعة مالية',
                        default => $state,
                    }),

                TextColumn::make('metadata')
                    ->label('تفاصيل العملية')
                    ->formatStateUsing(fn ($state) => empty($state) ? '-' : json_encode($state, JSON_UNESCAPED_UNICODE))
                    ->limit(50),

                TextColumn::make('ip_address')
                    ->label('عنوان IP')
                    ->default('-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('action')
                    ->label('نوع الحدث')
                    ->options([
                        'booking.created' => 'إنشاء حجز',
                        'return.processed' => 'معالجة إرجاع',
                        'collateral.released' => 'تسليم هوية',
                        'penalty.waived' => 'إعفاء غرامة',
                        'penalty.paid' => 'سداد غرامة',
                        'buffer.released' => 'انتهاء تنظيف',
                    ]),

                SelectFilter::make('actor_type')
                    ->label('نوع الفاعل')
                    ->options([
                        'user' => 'موظف',
                        'system' => 'النظام الآلي',
                    ]),
            ]);
    }
}
