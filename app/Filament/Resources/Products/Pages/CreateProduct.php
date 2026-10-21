<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Support\ProductVariantManager;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected array $extras = [];

    protected function beforeValidate(): void
    {
        ProductVariantManager::validate($this->data ?? []);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->extras = ProductVariantManager::extract($data);

        return $data;
    }

    protected function afterCreate(): void
    {
        ProductVariantManager::persist($this->record, $this->extras);
    }
}