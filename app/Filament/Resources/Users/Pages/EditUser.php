<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (User $record): bool => $record->role !== 'owner'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $actor = auth()->user();

        if ($actor->isOwner()) {
            if ($data['role'] !== 'staff') {
                throw ValidationException::withMessages([
                    'data.role' => 'لا يمكن لمالك المتجر ترقية موظف إلى مالك.',
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
                UserResource::ensureNoActiveOwner($data['tenant_id'], $this->record->getKey());
            }
        }

        return $data;
    }
}
