<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->formatStateUsing(fn (string $state): string => auth()->user()?->admin_role === 'super_admin' ? $state : (substr($state, 0, 1).'•••@'.(strrchr($state, '@') ? substr(strrchr($state, '@'), 1) : '')))
                    ->searchable(auth()->user()?->admin_role === 'super_admin'),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('verify_email')->label('Verify email')->requiresConfirmation()->visible(fn ($record) => ! $record->hasVerifiedEmail())->action(fn ($record) => $record->markEmailAsVerified()),
            ])
            ->toolbarActions([]);
    }
}
