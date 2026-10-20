<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\ProductVariant;
use App\Support\CategoryTree;
use App\Support\ImageProcessor;
use Closure;
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
                    ->options(fn (): array => CategoryTree::pathOptions())
                    ->required()
                    ->searchable()
                    ->helperText('A product can be placed in any category or sub-category.'),
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
                TextInput::make('sku')
                    ->label('SKU / product code (optional)')
                    ->maxLength(64)
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? trim((string) $state) : null)
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (filled($value) && ProductVariant::where('sku', trim((string) $value))->exists()) {
                            $fail('This code is already used by a variant of another product.');
                        }
                    })
                    ->helperText('Your own code, for example FQ-R-001. Must be unique. Leave empty if you do not use codes.'),
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
                TextInput::make('stock')
                    ->label('Stock (pieces available)')
                    ->required()
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(1000000)
                    ->default(0)
                    ->helperText('Customers cannot order more than this. When it reaches 0 the product shows "Sold out".'),
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