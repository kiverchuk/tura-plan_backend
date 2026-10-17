<?php

namespace App\Filament\Resources\FeatureFlags;

use App\Filament\Resources\AdminRecordResource;
use App\Filament\Resources\FeatureFlags\Pages\EditFeatureFlag;
use App\Filament\Resources\FeatureFlags\Pages\ListFeatureFlags;
use App\Filament\Resources\FeatureFlags\Schemas\FeatureFlagForm;
use App\Filament\Resources\FeatureFlags\Tables\FeatureFlagsTable;
use App\Models\FeatureFlag;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FeatureFlagResource extends AdminRecordResource
{
    protected static ?string $model = FeatureFlag::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static array $viewRoles = ['super_admin'];

    protected static array $manageRoles = ['super_admin'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return FeatureFlagForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FeatureFlagsTable::configure($table);
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
            'index' => ListFeatureFlags::route('/'),
            'edit' => EditFeatureFlag::route('/{record}/edit'),
        ];
    }
}
