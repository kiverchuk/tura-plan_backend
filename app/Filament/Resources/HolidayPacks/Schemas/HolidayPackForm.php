<?php

namespace App\Filament\Resources\HolidayPacks\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class HolidayPackForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('country')->required()->length(2)->upperCase(),
                TextInput::make('region')->maxLength(32),
                TextInput::make('year')->numeric()->required()->minValue(2000)->maxValue(2200),
                TextInput::make('locale')->required()->in(['en', 'ro', 'ru', 'uk', 'de', 'fr', 'es']),
                TextInput::make('version')->required()->maxLength(32),
                TextInput::make('source_url')->url()->required()->columnSpanFull(),
                Textarea::make('license')->required()->maxLength(2000)->columnSpanFull(),
                DatePicker::make('verified_at')->required(),
                Toggle::make('published')->helperText('Publish only after the official source, license and translations are verified.'),
            ]);
    }
}
