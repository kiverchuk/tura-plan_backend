<?php

namespace App\Filament\Resources\FeatureFlags\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FeatureFlagsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([TextColumn::make('key')->searchable(), IconColumn::make('enabled')->boolean(), TextColumn::make('updated_at')->dateTime()->sortable()])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
