<?php

namespace App\Filament\Resources\StoreWebhookEvents\Pages;

use App\Filament\Resources\StoreWebhookEvents\StoreWebhookEventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStoreWebhookEvents extends ListRecords
{
    protected static string $resource = StoreWebhookEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
