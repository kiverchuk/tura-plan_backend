<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\AdminRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                Select::make('admin_role')->options(collect(AdminRole::cases())->mapWithKeys(fn ($role) => [$role->value => $role->label()]))->nullable(),
                TextInput::make('email_verified_at')->disabled()->dehydrated(false),
            ]);
    }
}
