<?php

namespace App\Filament\Resources\HolidayPacks;

use App\Filament\Resources\AdminRecordResource;
use App\Filament\Resources\HolidayPacks\Pages\EditHolidayPack;
use App\Filament\Resources\HolidayPacks\Pages\ListHolidayPacks;
use App\Filament\Resources\HolidayPacks\Schemas\HolidayPackForm;
use App\Filament\Resources\HolidayPacks\Tables\HolidayPacksTable;
use App\Models\HolidayPack;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HolidayPackResource extends AdminRecordResource
{
    protected static ?string $model = HolidayPack::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Calendar content';

    protected static array $viewRoles = ['content_manager'];

    protected static array $manageRoles = ['content_manager'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return HolidayPackForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HolidayPacksTable::configure($table);
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
            'index' => ListHolidayPacks::route('/'),
            'edit' => EditHolidayPack::route('/{record}/edit'),
        ];
    }
}
