<?php

namespace App\Filament\Resources\SupportTickets\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SupportTicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('subject')->required()->maxLength(160),
                Select::make('category')->options(['account' => 'Account', 'billing' => 'Billing', 'calendar' => 'Calendar', 'privacy' => 'Privacy', 'bug' => 'Bug', 'other' => 'Other'])->required(),
                Select::make('status')->options(['open' => 'Open', 'in_progress' => 'In progress', 'resolved' => 'Resolved'])->required(),
                Textarea::make('message')->disabled()->dehydrated(false)->columnSpanFull(),
            ]);
    }
}
