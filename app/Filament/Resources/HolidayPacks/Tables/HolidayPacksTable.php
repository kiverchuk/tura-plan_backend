<?php

namespace App\Filament\Resources\HolidayPacks\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HolidayPacksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([TextColumn::make('country')->badge(), TextColumn::make('region')->placeholder('National'), TextColumn::make('year')->sortable(), TextColumn::make('locale')->badge(), TextColumn::make('version'), TextColumn::make('verified_at')->date()->sortable(), IconColumn::make('published')->boolean()])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
