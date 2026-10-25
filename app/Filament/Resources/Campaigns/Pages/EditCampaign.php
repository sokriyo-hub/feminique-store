<?php

namespace App\Filament\Resources\Campaigns\Pages;

use App\Filament\Resources\Campaigns\CampaignResource;
use App\Filament\Resources\Campaigns\Schemas\CampaignForm;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCampaign extends EditRecord
{
    protected static string $resource = CampaignResource::class;

    protected array $categoryIds = [];

    protected array $productIds = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['category_ids'] = $this->record->categories()->pluck('categories.id')->all();
        $data['product_ids'] = $this->record->products()->pluck('products.id')->all();
        $data['banner_slots'] = $this->record->bannerSlots();
        $data['offer_link'] = $this->record->offerUrl();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        [$this->categoryIds, $this->productIds] = CampaignForm::takeTargets($data);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->categories()->sync($this->categoryIds);
        $this->record->products()->sync($this->productIds);
    }
}