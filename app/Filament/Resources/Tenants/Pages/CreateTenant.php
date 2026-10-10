<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Filament\Resources\Tenants\TenantResource;
use App\Models\Tenant;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * @var array{name: string, email: string, password: string}
     */
    protected array $ownerCredentials = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->ownerCredentials = $data['owner'];
        unset($data['owner'], $data['tenant_id']);

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return DB::transaction(function () use ($data): Tenant {
                $tenant = Tenant::create($data);

                User::create([
                    'tenant_id' => $tenant->id,
                    'name' => $this->ownerCredentials['name'],
                    'email' => $this->ownerCredentials['email'],
                    'password' => $this->ownerCredentials['password'],
                    'role' => 'owner',
                    'is_active' => true,
                ]);

                $this->ownerCredentials = [];

                return $tenant;
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505' && str_contains($exception->getMessage(), 'users_email_unique')) {
                throw ValidationException::withMessages([
                    'data.owner.email' => 'هذا البريد الإلكتروني مستخدم بالفعل.',
                ]);
            }

            throw $exception;
        }
    }
}
