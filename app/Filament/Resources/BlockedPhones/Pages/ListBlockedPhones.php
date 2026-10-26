<?php

namespace App\Filament\Resources\BlockedPhones\Pages;

use App\Filament\Resources\BlockedPhones\BlockedPhoneResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBlockedPhones extends ListRecords
{
    protected static string $resource = BlockedPhoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Block a number'),
        ];
    }
}