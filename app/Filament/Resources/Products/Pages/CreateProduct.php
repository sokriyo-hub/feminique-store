<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected array $imagePaths = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->imagePaths = array_values($data['image_paths'] ?? []);
        unset($data['image_paths']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->syncImages($this->imagePaths);
    }
}