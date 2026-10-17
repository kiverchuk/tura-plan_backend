<?php

namespace App\Filament\Resources\PrivacyRequests\Pages;

use App\Filament\Resources\PrivacyRequests\PrivacyRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPrivacyRequests extends ListRecords
{
    protected static string $resource = PrivacyRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
