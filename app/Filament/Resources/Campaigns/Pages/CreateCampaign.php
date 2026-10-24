<?php

namespace App\Filament\Resources\Campaigns\Pages;

use App\Filament\Resources\Campaigns\CampaignResource;
use App\Filament\Resources\Campaigns\Schemas\CampaignForm;
use Filament\Resources\Pages\CreateRecord;

class CreateCampaign extends CreateRecord
{
    protected static string $resource = CampaignResource::class;

    protected array $categoryIds = [];

    protected array $productIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        [$this->categoryIds, $this->productIds] = CampaignForm::takeTargets($data);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->categories()->sync($this->categoryIds);
        $this->record->products()->sync($this->productIds);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}