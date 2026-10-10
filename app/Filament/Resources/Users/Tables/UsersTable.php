<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('اسم الموظف')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('email')
                    ->label('البريد الإلكتروني')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('role')
                    ->label('الدور')
                    ->badge()
                    ->colors([
                        'warning' => 'owner',
                        'info' => 'staff',
                        'danger' => 'system_admin',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'owner' => 'مالك المحل',
                        'staff' => 'موظف',
                        'system_admin' => 'مسؤول النظام',
                        default => $state,
                    })
                    ->sortable(),

                TextColumn::make('telegram_user_id')
                    ->label('معرف تيليجرام')
                    ->formatStateUsing(fn ($state) => $state ? (string) $state : 'غير مربوط')
                    ->searchable(),

                TextColumn::make('is_active')
                    ->label('الحالة')
                    ->badge()
                    ->colors([
                        'success' => true,
                        'danger' => false,
                    ])
                    ->formatStateUsing(fn (bool $state): string => $state ? 'نشط' : 'معطل')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('تاريخ الإضافة')
                    ->dateTime('Y-m-d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->label('تعديل')
                    ->visible(fn (User $record): bool => $record->role !== 'owner' || auth()->user()?->isSystemAdmin()),
            ]);
    }
}
