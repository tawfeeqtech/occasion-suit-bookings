<?php

namespace App\Filament\Widgets;

use App\Models\ItemMaintenance;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class CleaningBufferWidget extends BaseWidget
{
    protected static ?string $heading = 'قطع قيد التنظيف والتعقيم (Turnaround Buffer)';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ItemMaintenance::query()
                    ->where('status', 'cleaning')
                    ->with('item')
                    ->orderBy('expected_ready_at', 'asc')
            )
            ->columns([
                TextColumn::make('item.name')
                    ->label('القطعة / الموديل')
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('item.category')
                    ->label('التصنيف')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'suit' => 'بدلة كاملة',
                        'shirt' => 'قميص',
                        'shoes' => 'حذاء',
                        'belt' => 'حزام',
                        'tie' => 'ربطة عنق',
                        'vest' => 'صديري',
                        'lapel_pin' => 'إكسسوار',
                        default => $state,
                    }),

                TextColumn::make('item.size')
                    ->label('المقاس'),

                TextColumn::make('started_at')
                    ->label('تاريخ بدء التنظيف')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                TextColumn::make('expected_ready_at')
                    ->label('تاريخ الجاهزية المتوقع')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                TextColumn::make('readiness_countdown')
                    ->label('حالة الجاهزية')
                    ->badge()
                    ->state(function (ItemMaintenance $record): string {
                        if ($record->expected_ready_at->isPast()) {
                            return 'جاهز للتحرير والفرز';
                        }

                        return $record->expected_ready_at->diffForHumans();
                    })
                    ->colors([
                        'success' => fn ($state) => $state === 'جاهز للتحرير والفرز',
                        'warning' => fn ($state) => $state !== 'جاهز للتحرير والفرز',
                    ]),
            ])
            ->paginated(false);
    }
}
