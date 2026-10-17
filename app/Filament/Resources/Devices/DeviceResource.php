<?php

namespace App\Filament\Resources\Devices;

use App\Filament\Resources\AdminRecordResource;
use App\Filament\Resources\Devices\Pages\ListDevices;
use App\Filament\Resources\Devices\Schemas\DeviceForm;
use App\Filament\Resources\Devices\Tables\DevicesTable;
use App\Models\Device;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DeviceResource extends AdminRecordResource
{
    protected static ?string $model = Device::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Access & security';

    protected static ?string $navigationLabel = 'Devices & sessions';

    protected static array $viewRoles = ['super_admin', 'support', 'privacy_officer'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return DeviceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DevicesTable::configure($table);
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
            'index' => ListDevices::route('/'),
        ];
    }
}
