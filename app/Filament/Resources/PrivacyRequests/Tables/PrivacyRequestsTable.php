<?php

namespace App\Filament\Resources\PrivacyRequests\Tables;

use App\Jobs\ProcessPrivacyRequest;
use App\Models\PrivacyRequest;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PrivacyRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([TextColumn::make('id')->limit(16), TextColumn::make('user_id')->label('Account')->formatStateUsing(fn ($state) => '••'.substr((string) $state, -4)), TextColumn::make('kind')->badge(), TextColumn::make('status')->badge(), TextColumn::make('created_at')->dateTime()->sortable(), TextColumn::make('completed_at')->dateTime()->placeholder('—')])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('retry')->requiresConfirmation()->visible(fn (PrivacyRequest $record) => $record->status === 'failed')->action(fn (PrivacyRequest $record) => ProcessPrivacyRequest::dispatch($record->id)),
            ])->toolbarActions([]);
    }
}
