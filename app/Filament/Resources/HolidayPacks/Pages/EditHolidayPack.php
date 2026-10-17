<?php

namespace App\Filament\Resources\HolidayPacks\Pages;

use App\Filament\Resources\HolidayPacks\HolidayPackResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHolidayPack extends EditRecord
{
    protected static string $resource = HolidayPackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
