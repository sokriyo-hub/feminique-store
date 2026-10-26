<?php

namespace App\Filament\Resources\BlockedPhones\Pages;

use App\Filament\Resources\BlockedPhones\BlockedPhoneResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBlockedPhone extends ViewRecord
{
    protected static string $resource = BlockedPhoneResource::class;

    // The order list is wide: use the whole width
    public function getMaxContentWidth(): ?string
    {
        return 'full';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Edit'),
            DeleteAction::make()
                ->label('Unblock number')
                ->modalHeading('Unblock this number?'),
        ];
    }
}