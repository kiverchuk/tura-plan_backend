<?php

namespace App\Filament\Resources\Devices\Tables;

use App\Models\Device;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class DevicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([TextColumn::make('user_id')->label('Account')->formatStateUsing(fn ($state) => '••'.substr((string) $state, -4)), TextColumn::make('name')->limit(30), TextColumn::make('platform')->badge(), TextColumn::make('app_version')->label('App version'), TextColumn::make('last_seen_at')->dateTime()->sortable(), TextColumn::make('revoked_at')->dateTime()->placeholder('Active')])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('revoke')->color('danger')->requiresConfirmation()->visible(fn (Device $record) => ! $record->revoked_at)->action(function (Device $record) {
                    $record->update(['revoked_at' => now()]);
                    DB::table('personal_access_tokens')->where('name', $record->id)->delete();
                }),
            ])->toolbarActions([]);
    }
}
