<?php

namespace App\Filament\Resources\StoreWebhookEvents\Pages;

use App\Filament\Resources\StoreWebhookEvents\StoreWebhookEventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStoreWebhookEvent extends CreateRecord
{
    protected static string $resource = StoreWebhookEventResource::class;
}
