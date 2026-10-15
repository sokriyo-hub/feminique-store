<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Support\ImageProcessor;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, ?string $state, Set $set) {
                        if ($operation === 'create') {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->helperText('English letters, numbers and dashes only. It is used in the web link.'),
                Textarea::make('description')
                    ->rows(5)
                    ->maxLength(5000)
                    ->columnSpanFull(),
                TextInput::make('price')
                    ->label('Regular price')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->prefix('Tk'),
                TextInput::make('discount_price')
                    ->label('Discounted price (optional)')
                    ->numeric()
                    ->minValue(0)
                    ->lt('price')
                    ->prefix('Tk')
                    ->helperText('Leave empty if there is no discount. Discount % is calculated automatically.'),
                FileUpload::make('image_paths')
                    ->label('Images')
                    ->multiple()
                    ->reorderable()
                    ->appendFiles()
                    ->disk('public')
                    ->directory('products')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(2048)
                    ->maxFiles(8)
                    ->panelLayout('grid')
                    ->saveUploadedFileUsing(
                        fn (TemporaryUploadedFile $file): string => ImageProcessor::storeAsWebp($file->getRealPath(), 'products')
                    )
                    ->helperText('JPG, PNG or WebP. Up to 8 images, max 2 MB each. Images are resized and converted to WebP automatically. The first image is the main image. Drag to change the order.')
                    ->columnSpanFull(),
            ]);
    }
}