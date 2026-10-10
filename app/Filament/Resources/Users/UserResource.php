<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'فريق العمل وتيليجرام';

    protected static ?string $modelLabel = 'موظف';

    protected static ?string $pluralModelLabel = 'فريق العمل';

    protected static ?int $navigationSort = 4;

    /**
     * Strict RBAC: Staff are forbidden from managing users. Only Owners and System Admins.
     */
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->isOwner() || $user->isSystemAdmin());
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();

        if ($user === null || $record->role === 'system_admin') {
            return false;
        }

        return $user->isSystemAdmin()
            || ($user->isOwner() && $record->role === 'staff' && $record->tenant_id === $user->tenant_id);
    }

    public static function canDelete($record): bool
    {
        $user = auth()->user();

        if ($user === null || $record->role === 'owner' || $record->role === 'system_admin') {
            return false;
        }

        return $user->isSystemAdmin()
            || ($user->isOwner() && $record->tenant_id === $user->tenant_id);
    }

    public static function ensureNoActiveOwner(string $tenantId, ?string $exceptUserId = null): void
    {
        $query = User::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('role', 'owner')
            ->where('is_active', true);

        if ($exceptUserId !== null) {
            $query->whereKeyNot($exceptUserId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'data.tenant_id' => 'يوجد مالك نشط لهذا المتجر. عطّل الحساب الحالي قبل إنشاء مالك بديل.',
            ]);
        }
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
