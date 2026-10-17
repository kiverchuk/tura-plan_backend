<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

abstract class AdminRecordResource extends Resource
{
    protected static array $viewRoles = [];

    protected static array $manageRoles = [];

    public static function canViewAny(): bool
    {
        return in_array(auth()->user()?->admin_role, static::$viewRoles, true) || in_array(auth()->user()?->admin_role, static::$manageRoles, true);
    }

    public static function canCreate(): bool
    {
        return in_array(auth()->user()?->admin_role, static::$manageRoles, true);
    }

    public static function canEdit(Model $record): bool
    {
        return in_array(auth()->user()?->admin_role, static::$manageRoles, true);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
