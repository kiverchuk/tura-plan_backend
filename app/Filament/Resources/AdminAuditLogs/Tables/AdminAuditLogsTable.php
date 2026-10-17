<?php

namespace App\Filament\Resources\AdminAuditLogs\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdminAuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([TextColumn::make('action')->badge(), TextColumn::make('subject_type')->label('Route / resource')->limit(60), TextColumn::make('subject_id')->label('Record')->limit(24), TextColumn::make('admin_user_id')->label('Operator')->toggleable(), TextColumn::make('created_at')->dateTime()->sortable()])
            ->filters([
                //
            ])
            ->recordActions([])->toolbarActions([]);
    }
}
