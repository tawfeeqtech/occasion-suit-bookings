<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AuditLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tenant_id')
                    ->relationship('tenant', 'name')
                    ->required(),
                TextInput::make('actor_type')
                    ->required(),
                TextInput::make('actor_id')
                    ->required(),
                TextInput::make('action')
                    ->required(),
                TextInput::make('entity_type')
                    ->required(),
                TextInput::make('entity_id'),
                TextInput::make('metadata')
                    ->required()
                    ->default('{}'),
                TextInput::make('ip_address'),
            ]);
    }
}
