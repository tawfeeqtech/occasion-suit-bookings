<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use Carbon\Carbon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TodayScheduleWidget extends BaseWidget
{
    protected static ?string $heading = 'جدول مواعيد اليوم (تسليم واسترجاع)';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $today = Carbon::today()->toDateString();

        return $table
            ->query(
                Booking::query()
                    ->where(function ($query) use ($today) {
                        $query->whereDate('pickup_date', $today)
                            ->orWhereDate('return_date', $today);
                    })
                    ->whereIn('status', ['active', 'damage_pending', 'overdue'])
                    ->latest()
            )
            ->columns([
                TextColumn::make('booking_number')
                    ->label('رقم الحجز')
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('customer_name')
                    ->label('اسم العميل')
                    ->searchable(),

                TextColumn::make('customer_phone')
                    ->label('الهاتف'),

                TextColumn::make('schedule_type')
                    ->label('نوع الموعد')
                    ->badge()
                    ->state(function (Booking $record) use ($today): string {
                        if ($record->pickup_date?->toDateString() === $today) {
                            return 'تسليم بدلة للعميل';
                        }

                        return 'استلام وفحص مرتجع';
                    })
                    ->colors([
                        'primary' => fn ($state) => $state === 'تسليم بدلة للعميل',
                        'warning' => fn ($state) => $state === 'استلام وفحص مرتجع',
                    ]),

                TextColumn::make('remaining_balance')
                    ->label('الرصيد المتبقي')
                    ->formatStateUsing(fn ($state) => '₪ '.number_format((float) $state, 2)),

                TextColumn::make('status')
                    ->label('حالة الحجز')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'نشط',
                        'damage_pending' => 'معلق (تلف)',
                        'overdue' => 'متأخر',
                        default => $state,
                    }),
            ])
            ->paginated(false);
    }
}
