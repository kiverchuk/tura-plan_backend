<?php

namespace App\Filament\Resources\PrivacyRequests\Pages;

use App\Filament\Resources\PrivacyRequests\PrivacyRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPrivacyRequest extends EditRecord
{
    protected static string $resource = PrivacyRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
