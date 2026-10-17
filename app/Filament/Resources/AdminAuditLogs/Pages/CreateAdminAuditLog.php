<?php

namespace App\Filament\Resources\AdminAuditLogs\Pages;

use App\Filament\Resources\AdminAuditLogs\AdminAuditLogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdminAuditLog extends CreateRecord
{
    protected static string $resource = AdminAuditLogResource::class;
}
