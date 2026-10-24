<?php

namespace App\Filament\Resources\Ads\Pages;

use App\Filament\Pages\ManageAdSlots;
use App\Filament\Resources\Ads\AdResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListAds extends ListRecords
{
    protected static string $resource = AdResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('places')
                ->label('Ad places')
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->color('gray')
                ->url(ManageAdSlots::getUrl()),
            CreateAction::make(),
        ];
    }
}