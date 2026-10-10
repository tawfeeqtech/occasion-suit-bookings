<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $actor = auth()->user();

        if ($actor->isOwner()) {
            if ($data['role'] !== 'staff') {
                throw ValidationException::withMessages([
                    'data.role' => 'لا يمكن لمالك المتجر إنشاء حساب مالك آخر.',
                ]);
            }

            $data['tenant_id'] = $actor->tenant_id;
        } else {
            if (! in_array($data['role'], ['staff', 'owner'], true)) {
                throw ValidationException::withMessages([
                    'data.role' => 'الدور المحدد غير صالح.',
                ]);
            }

            if ($data['role'] === 'owner') {
                UserResource::ensureNoActiveOwner($data['tenant_id']);
            }
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): User
    {
        return User::create($data);
    }
}
