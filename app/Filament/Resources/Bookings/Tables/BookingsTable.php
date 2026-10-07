<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Services\ReturnService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('booking_number')
                    ->label('رقم الحجز')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('customer_name')
                    ->label('اسم العميل')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer_phone')
                    ->label('الهاتف')
                    ->searchable(),

                TextColumn::make('pickup_date')
                    ->label('الاستلام')
                    ->date('Y-m-d')
                    ->sortable(),

                TextColumn::make('return_date')
                    ->label('الإرجاع')
                    ->date('Y-m-d')
                    ->sortable(),

                TextColumn::make('total_fee')
                    ->label('الإجمالي')
                    ->formatStateUsing(fn ($state) => '₪ '.number_format((float) $state, 2))
                    ->sortable(),

                TextColumn::make('remaining_balance')
                    ->label('المتبقي')
                    ->formatStateUsing(fn ($state) => '₪ '.number_format((float) $state, 2))
                    ->sortable(),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->colors([
                        'info' => 'active',
                        'success' => 'completed',
                        'danger' => 'damage_pending',
                        'warning' => 'overdue',
                        'gray' => 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'نشط',
                        'completed' => 'مكتمل',
                        'damage_pending' => 'معلق (تلف)',
                        'overdue' => 'متأخر',
                        'cancelled' => 'ملغى',
                        default => $state,
                    })
                    ->sortable(),

                TextColumn::make('collateralRecord.status')
                    ->label('بطاقة الهوية')
                    ->badge()
                    ->colors([
                        'warning' => 'held',
                        'success' => 'released',
                    ])
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'held' => 'الهوية محتجزة',
                        'released' => 'الهوية مُسلّمة',
                        default => 'غير مسجل',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('حالة الحجز')
                    ->options([
                        'active' => 'نشط',
                        'completed' => 'مكتمل',
                        'damage_pending' => 'معلق (تلف)',
                        'overdue' => 'متأخر',
                        'cancelled' => 'ملغى',
                    ]),
            ])
            ->recordActions([
                // 1. Process Return Action
                Action::make('process_return')
                    ->label('فحص وإرجاع')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->color('warning')
                    ->visible(fn (Booking $record): bool => $record->status !== 'completed')
                    ->form(function (Booking $record) {
                        $itemsData = $record->bookingItems->map(fn ($bi) => [
                            'booking_item_id' => $bi->id,
                            'item_name' => $bi->item?->name ?? 'قطعة',
                            'return_status' => 'clean_pass',
                            'penalty_fee' => 0.00,
                            'penalty_reason' => null,
                            'notes' => null,
                        ])->toArray();

                        return [
                            Repeater::make('items')
                                ->label('فحص مفردات الحجز')
                                ->schema([
                                    TextInput::make('item_name')
                                        ->label('القطعة')
                                        ->disabled()
                                        ->dehydrated(false),

                                    Hidden::make('booking_item_id'),

                                    Select::make('return_status')
                                        ->label('حالة الفحص')
                                        ->options([
                                            'clean_pass' => 'سليم (Clean Pass)',
                                            'damaged' => 'تالف (Damaged)',
                                            'missing' => 'مفقود (Missing)',
                                        ])
                                        ->default('clean_pass')
                                        ->required(),

                                    TextInput::make('penalty_fee')
                                        ->label('مبلغ الغرامة التقديري (₪)')
                                        ->numeric()
                                        ->minValue(0)
                                        ->default(0),

                                    TextInput::make('penalty_reason')
                                        ->label('سبب الغرامة والتقدير'),

                                    TextInput::make('notes')
                                        ->label('ملاحظات الفحص'),
                                ])
                                ->default($itemsData)
                                ->disableItemCreation()
                                ->disableItemDeletion()
                                ->columnSpanFull(),

                            TextInput::make('remaining_balance_collected')
                                ->label('المبلغ المحصل من الرصيد المتبقي (₪)')
                                ->numeric()
                                ->minValue(0)
                                ->default($record->remaining_balance),

                            TextInput::make('penalty_collected')
                                ->label('المبلغ المحصل من الغرامات (₪)')
                                ->numeric()
                                ->minValue(0)
                                ->default(0),

                            Select::make('payment_method')
                                ->label('طريقة الدفع للمبالغ المحصلة')
                                ->options([
                                    'cash' => 'نقداً (كاش)',
                                    'palpay' => 'PalPay',
                                    'jawwal_pay' => 'Jawwal Pay',
                                    'bank_transfer' => 'تحويل بنكي',
                                ])
                                ->default('cash'),

                            Checkbox::make('release_collateral')
                                ->label('تسليم بطاقة الهوية للزبون فوراً (في حال براءة الذمة)'),
                        ];
                    })
                    ->action(function (Booking $record, array $data) {
                        try {
                            app(ReturnService::class)->processReturn($record, $data, auth()->user());

                            Notification::make()
                                ->title('تمت معالجة الإرجاع وفحص القطع بنجاح')
                                ->success()
                                ->send();
                        } catch (ValidationException $e) {
                            if (app()->environment('testing')) {
                                throw $e;
                            }

                            $firstError = collect($e->validator->errors()->all())->first();
                            Notification::make()
                                ->title('تعذر إتمام الإرجاع')
                                ->body($firstError)
                                ->danger()
                                ->send();
                        }
                    }),

                // 2. Release Collateral Action
                Action::make('release_collateral')
                    ->label('تسليم الهوية')
                    ->icon('heroicon-o-identification')
                    ->color('success')
                    ->visible(fn (Booking $record): bool => $record->collateralRecord?->status === 'held')
                    ->requiresConfirmation()
                    ->modalHeading('تسليم بطاقة الهوية للزبون')
                    ->modalDescription('سيتم التحقق آلياً من عدم وجود أي رصيد إيجار متبقٍ أو غرامات تلف غير مسددة قبل تسليم الهوية.')
                    ->action(function (Booking $record) {
                        try {
                            app(ReturnService::class)->releaseCollateral($record, auth()->user());

                            Notification::make()
                                ->title('تم تسليم بطاقة الهوية بنجاح')
                                ->success()
                                ->send();
                        } catch (ValidationException $e) {
                            $firstError = collect($e->validator->errors()->all())->first();
                            Notification::make()
                                ->title('محظور تسليم الهوية')
                                ->body($firstError)
                                ->danger()
                                ->send();
                        }
                    }),

                // 3. Waive Penalty Action (Owner only)
                Action::make('waive_penalty')
                    ->label('إعفاء غرامة')
                    ->icon('heroicon-o-shield-check')
                    ->color('danger')
                    ->visible(fn (Booking $record): bool => auth()->user()?->isOwner() && $record->bookingItems()->where('is_waived', false)->whereIn('inspection_status', ['damaged', 'missing'])->exists())
                    ->form(function (Booking $record) {
                        $items = $record->bookingItems()
                            ->where('is_waived', false)
                            ->whereIn('inspection_status', ['damaged', 'missing'])
                            ->get()
                            ->mapWithKeys(fn ($bi) => [$bi->id => sprintf('%s - غرامة ₪%s (%s)', $bi->item?->name ?? 'قطعة', $bi->penalty_fee, $bi->penalty_reason)]);

                        return [
                            Select::make('booking_item_id')
                                ->label('القطعة المراد إعفاء غرامتها')
                                ->options($items)
                                ->required(),

                            TextInput::make('reason')
                                ->label('سبب الإعفاء المعتمد من المالك')
                                ->required()
                                ->maxLength(255),
                        ];
                    })
                    ->action(function (Booking $record, array $data) {
                        $bookingItem = BookingItem::findOrFail($data['booking_item_id']);
                        app(ReturnService::class)->waivePenalty($bookingItem, auth()->user(), $data['reason']);

                        Notification::make()
                            ->title('تم إعفاء الغرامة بنجاح')
                            ->success()
                            ->send();
                    }),

                EditAction::make()->label('تعديل'),
            ]);
    }
}
