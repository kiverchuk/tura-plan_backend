<?php

namespace App\Filament\Resources\SupportTickets\Tables;

use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SupportTicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([TextColumn::make('subject')->searchable()->limit(60), TextColumn::make('category')->badge(), SelectColumn::make('status')->options(['open' => 'Open', 'in_progress' => 'In progress', 'resolved' => 'Resolved'])->selectable(), TextColumn::make('message')->limit(70)->wrap(), TextColumn::make('created_at')->dateTime()->sortable()])
            ->filters([
                //
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
