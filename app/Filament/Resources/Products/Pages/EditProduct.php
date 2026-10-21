<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Support\ProductVariantManager;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected array $extras = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return ProductVariantManager::fill($data, $this->record);
    }

    protected function beforeValidate(): void
    {
        ProductVariantManager::validate($this->data ?? [], $this->record);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->extras = ProductVariantManager::extract($data);

        return $data;
    }

    protected function afterSave(): void
    {
        ProductVariantManager::persist($this->record, $this->extras);
    }
}