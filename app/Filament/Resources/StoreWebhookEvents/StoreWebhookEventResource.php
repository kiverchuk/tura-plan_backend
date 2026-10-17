<?php

namespace App\Filament\Resources\StoreWebhookEvents;

use App\Filament\Resources\AdminRecordResource;
use App\Filament\Resources\StoreWebhookEvents\Pages\ListStoreWebhookEvents;
use App\Filament\Resources\StoreWebhookEvents\Schemas\StoreWebhookEventForm;
use App\Filament\Resources\StoreWebhookEvents\Tables\StoreWebhookEventsTable;
use App\Models\StoreWebhookEvent;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StoreWebhookEventResource extends AdminRecordResource
{
    protected static ?string $model = StoreWebhookEvent::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Store webhook status';

    protected static array $viewRoles = ['super_admin', 'billing'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return StoreWebhookEventForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StoreWebhookEventsTable::configure($table);
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
            'index' => ListStoreWebhookEvents::route('/'),
        ];
    }
}
