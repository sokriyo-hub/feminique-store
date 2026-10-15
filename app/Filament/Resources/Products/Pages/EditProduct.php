<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected array $imagePaths = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['image_paths'] = $this->record->images()->pluck('path')->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->imagePaths = array_values($data['image_paths'] ?? []);
        unset($data['image_paths']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->syncImages($this->imagePaths);
    }
}