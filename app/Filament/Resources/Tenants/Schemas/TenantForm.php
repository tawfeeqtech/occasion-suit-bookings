<?php

namespace App\Filament\Resources\Tenants\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('معلومات المتجر')
                    ->schema([
                        TextInput::make('name')
                            ->label('اسم المتجر / المحل')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('slug')
                            ->label('المعرف الفريد (Slug)')
                            ->required()
                            ->disabled(fn (): bool => ! auth()->user()?->isSystemAdmin())
                            ->maxLength(255),

                        Toggle::make('is_active')
                            ->label('اشتراك المتجر نشط')
                            ->default(true)
                            ->visible(fn (): bool => (bool) auth()->user()?->isSystemAdmin()),
                    ]),

                Section::make('حساب مالك المتجر')
                    ->schema([
                        TextInput::make('owner.name')
                            ->label('اسم المالك')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('owner.email')
                            ->label('البريد الإلكتروني للمالك')
                            ->email()
                            ->required()
                            ->unique(table: 'users', column: 'email')
                            ->maxLength(255),

                        TextInput::make('owner.password')
                            ->label('كلمة المرور الأولية للمالك')
                            ->password()
                            ->required()
                            ->minLength(8)
                            ->maxLength(255)
                            ->dehydrated(),
                    ])
                    ->visible(fn (string $operation): bool => $operation === 'create'),

                Section::make('إعدادات التشغيل والسياسات')
                    ->schema([
                        TextInput::make('settings.buffer_hours')
                            ->label('فترة التنظيف والتعقيم الافتراضية (بالساعات)')
                            ->helperText('المدة التلقائية التي تدخل فيها القطع مرحلة التنظيف بعد الإرجاع قبل إتاحتها للحجز مجدداً')
                            ->numeric()
                            ->default(48)
                            ->minValue(1)
                            ->required(),

                        Select::make('settings.currency')
                            ->label('العملة المعتمدة للمتجر')
                            ->options([
                                'ILS' => 'شيكل إسرائيلي (₪)',
                                'JOD' => 'دينار أردني (JOD)',
                                'USD' => 'دولار أمريكي ($)',
                            ])
                            ->default('ILS')
                            ->required(),
                    ]),
            ]);
    }
}
