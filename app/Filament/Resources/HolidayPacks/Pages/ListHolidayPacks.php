<?php

namespace App\Filament\Resources\HolidayPacks\Pages;

use App\Filament\Resources\HolidayPacks\HolidayPackResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHolidayPacks extends ListRecords
{
    protected static string $resource = HolidayPackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
