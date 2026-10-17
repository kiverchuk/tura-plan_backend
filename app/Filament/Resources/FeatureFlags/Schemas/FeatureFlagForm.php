<?php

namespace App\Filament\Resources\FeatureFlags\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FeatureFlagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')->required()->regex('/^[a-z][a-z0-9_.-]{1,79}$/')->disabled(fn ($record) => $record !== null),
                Toggle::make('enabled')->required(),
                Textarea::make('config')->helperText('JSON only. Do not store personal data or credentials.'),
            ]);
    }
}
