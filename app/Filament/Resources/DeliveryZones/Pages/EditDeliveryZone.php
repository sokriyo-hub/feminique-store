<?php

namespace App\Filament\Resources\DeliveryZones\Pages;

use App\Filament\Resources\DeliveryZones\DeliveryZoneResource;
use App\Models\DeliveryZone;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDeliveryZone extends EditRecord
{
    protected static string $resource = DeliveryZoneResource::class;

    // শেষ active জোন বন্ধ করা যাবে না
    protected function beforeSave(): void
    {
        $becomingInactive = empty($this->data['is_active']);

        $otherActiveExists = DeliveryZone::where('is_active', true)
            ->where('id', '!=', $this->record->id)
            ->exists();

        if ($becomingInactive && ! $otherActiveExists) {
            Notification::make()
                ->title('At least one zone must stay active')
                ->body('Activate or add another zone before deactivating this one.')
                ->danger()
                ->send();

            $this->halt();
        }
    }
}