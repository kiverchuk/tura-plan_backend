<?php

namespace App\Filament\Resources\PrivacyRequests;

use App\Filament\Resources\AdminRecordResource;
use App\Filament\Resources\PrivacyRequests\Pages\ListPrivacyRequests;
use App\Filament\Resources\PrivacyRequests\Schemas\PrivacyRequestForm;
use App\Filament\Resources\PrivacyRequests\Tables\PrivacyRequestsTable;
use App\Models\PrivacyRequest;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PrivacyRequestResource extends AdminRecordResource
{
    protected static ?string $model = PrivacyRequest::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Privacy & support';

    protected static ?string $navigationLabel = 'Privacy requests';

    protected static array $viewRoles = ['super_admin', 'privacy_officer'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return PrivacyRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PrivacyRequestsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrivacyRequests::route('/'),
        ];
    }
}
