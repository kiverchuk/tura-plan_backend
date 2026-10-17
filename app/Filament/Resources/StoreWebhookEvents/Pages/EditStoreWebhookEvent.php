<?php

namespace App\Filament\Resources\StoreWebhookEvents\Pages;

use App\Filament\Resources\StoreWebhookEvents\StoreWebhookEventResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStoreWebhookEvent extends EditRecord
{
    protected static string $resource = StoreWebhookEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
