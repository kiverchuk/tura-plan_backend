<?php

namespace App\Filament\Resources\AdminAuditLogs;

use App\Filament\Resources\AdminAuditLogs\Pages\ListAdminAuditLogs;
use App\Filament\Resources\AdminAuditLogs\Schemas\AdminAuditLogForm;
use App\Filament\Resources\AdminAuditLogs\Tables\AdminAuditLogsTable;
use App\Filament\Resources\AdminRecordResource;
use App\Models\AdminAuditLog;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AdminAuditLogResource extends AdminRecordResource
{
    protected static ?string $model = AdminAuditLog::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Access & security';

    protected static array $viewRoles = ['super_admin', 'support', 'billing', 'content_manager', 'privacy_officer', 'auditor'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return AdminAuditLogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdminAuditLogsTable::configure($table);
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
            'index' => ListAdminAuditLogs::route('/'),
        ];
    }
}
