<?php

namespace App\Filament\Resources\StoreWebhookEvents\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StoreWebhookEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([TextColumn::make('store')->badge(), TextColumn::make('event_id')->limit(24), TextColumn::make('status')->badge(), TextColumn::make('error_code')->placeholder('—'), TextColumn::make('received_at')->dateTime()->sortable(), TextColumn::make('processed_at')->dateTime()->placeholder('—')])
            ->filters([
                //
            ])
            ->recordActions([

            ])->toolbarActions([]);
    }
}
