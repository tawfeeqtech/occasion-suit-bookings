<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Tenant;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('اسم الموظف')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('البريد الإلكتروني')
                    ->email()
                    ->required()
                    ->unique(table: 'users', column: 'email', ignoreRecord: true)
                    ->maxLength(255),

                TextInput::make('password')
                    ->label('كلمة المرور')
                    ->password()
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make($state) : null)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create'),

                Select::make('role')
                    ->label('الدور والصلاحية')
                    ->options(fn (): array => auth()->user()?->isSystemAdmin()
                        ? [
                            'staff' => 'موظف محل (Staff)',
                            'owner' => 'مالك المحل (Owner)',
                        ]
                        : ['staff' => 'موظف محل (Staff)'])
                    ->default('staff')
                    ->required(),

                Select::make('tenant_id')
                    ->label('المتجر')
                    ->options(fn (): array => Tenant::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->visible(fn (): bool => (bool) auth()->user()?->isSystemAdmin())
                    ->required(fn (): bool => (bool) auth()->user()?->isSystemAdmin())
                    ->rule('exists:tenants,id'),

                TextInput::make('telegram_user_id')
                    ->label('معرف تيليجرام (Telegram User ID)')
                    ->numeric()
                    ->helperText('المعرف الرقمي للموظف في تيليجرام لمصادقة الرسائل الصوتية'),

                Toggle::make('is_active')
                    ->label('حساب نشط ومفعل')
                    ->default(true)
                    ->required(),
            ]);
    }
}
