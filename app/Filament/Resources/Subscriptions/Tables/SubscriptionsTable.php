<?php

namespace App\Filament\Resources\Subscriptions\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([TextColumn::make('user_id')->label('Account')->formatStateUsing(fn ($state) => '••'.substr((string) $state, -4)), TextColumn::make('store')->badge(), TextColumn::make('product_id')->limit(35), TextColumn::make('status')->badge(), TextColumn::make('expires_at')->dateTime()->sortable(), TextColumn::make('revoked_at')->dateTime()->placeholder('—')])
            ->filters([
                //
            ])
            ->recordActions([

            ])->toolbarActions([]);
    }
}
