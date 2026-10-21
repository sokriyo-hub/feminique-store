<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\ProductVariant;
use App\Support\CategoryTree;
use App\Support\ImageProcessor;
use App\Support\ProductVariantManager;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
                    ->prefix('Tk')
                    ->helperText('If the product has variants, this price is used for every variant that has no price of its own.'),
                TextInput::make('discount_price')
                    ->label('Discounted price (optional)')
                    ->numeric()
                    ->minValue(0)
                    ->lt('price')
                    ->prefix('Tk')
                    ->helperText('Leave empty if there is no discount. Discount % is calculated automatically.'),

                Toggle::make('has_variants')
                    ->label('This product has variants (size, colour, length ...)')
                    ->default(false)
                    ->live()
                    ->helperText('Turn on when customers must choose something, for example ring size or colour. Each combination gets its own stock and, if you wish, its own price. Turning this off later deletes all variants of this product when you save.')
                    ->columnSpanFull(),

                // Simple product (no variants): one stock number
                TextInput::make('stock')
                    ->label('Stock (pieces available)')
                    ->required()
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(1000000)
                    ->default(0)
                    ->visible(fn (Get $get): bool => ! $get('has_variants'))
                    ->helperText('Customers cannot order more than this. When it reaches 0 the product shows "Sold out".'),

                self::variantsSection(),

                FileUpload::make('image_paths')
                    ->label('Images')
                    ->multiple()
                    ->reorderable()
                    ->appendFiles()
                    ->disk('public')
                    ->directory('products')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(2048)
                    ->maxFiles(ProductVariantManager::MAX_IMAGE_SLOTS)
                    ->panelLayout('grid')
                    ->saveUploadedFileUsing(
                        fn (TemporaryUploadedFile $file): string => ImageProcessor::storeAsWebp($file->getRealPath(), 'products')
                    )
                    ->helperText('JPG, PNG or WebP. Up to 8 images, max 2 MB each. Images are resized and converted to WebP automatically. The first image is the main image. Drag to change the order.')
                    ->columnSpanFull(),

                self::imageChoiceSection(),
            ]);
    }

    // Options + generated combinations
    private static function variantsSection(): Section
    {
        return Section::make('Variants')
            ->description('Step 1: add the options (for example Size and Color) and their choices. Step 2: click "Generate combinations". Step 3: fill SKU, price and stock for each row. Delete a row if that combination does not exist.')
            ->visible(fn (Get $get): bool => (bool) $get('has_variants'))
            ->columnSpanFull()
            ->schema([
                Repeater::make('options')
                    ->label('Options (up to 3)')
                    ->schema([
                        TextInput::make('name')
                            ->label('Option name')
                            ->placeholder('Size, Color, Length ...')
                            ->required()
                            ->maxLength(50),
                        TagsInput::make('choices')
                            ->label('Choices')
                            ->placeholder('Type a choice, press Enter')
                            ->required(),
                    ])
                    ->columns(2)
                    ->maxItems(3)
                    ->defaultItems(0)
                    ->addActionLabel('Add option')
                    ->helperText('If you rename a choice, it counts as a new combination and its stock starts from 0. Do not use the signs = or | in names.'),

                Actions::make([
                    Action::make('generateVariants')
                        ->label('Generate combinations')
                        ->color('gray')
                        ->action(function (Get $get, Set $set): void {
                            [$rows, $error] = ProductVariantManager::generateRows(
                                (array) $get('options'),
                                (array) $get('variants'),
                            );

                            if ($error) {
                                Notification::make()->danger()->title('Cannot generate combinations')->body($error)->send();

                                return;
                            }

                            $set('variants', $rows);

                            Notification::make()
                                ->success()
                                ->title(count($rows) . ' combination(s) ready')
                                ->body('Now fill the stock (and price, SKU if needed) of each row.')
                                ->send();
                        }),
                ]),

                Repeater::make('variants')
                    ->label('Variants (one row per combination)')
                    ->schema([
                        Hidden::make('combination'),
                        TextInput::make('label')
                            ->label('Combination')
                            ->readOnly()
                            ->dehydrated(false)
                            ->columnSpan(['default' => 2, 'lg' => 2]),
                        TextInput::make('sku')
                            ->label('SKU')
                            ->maxLength(64)
                            ->alphaDash(),
                        TextInput::make('price')
                            ->label('Price')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('Tk')
                            ->placeholder('Product price'),
                        TextInput::make('discount_price')
                            ->label('Discounted price')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('Tk'),
                        TextInput::make('stock')
                            ->label('Stock')
                            ->required()
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(1000000)
                            ->default(0),
                    ])
                    ->columns(['default' => 2, 'lg' => 7])
                    ->addable(false)
                    ->reorderable(false)
                    ->defaultItems(0)
                    ->helperText('Leave Price empty to use the product price. Stock 0 shows "Sold out" for that combination.'),
            ]);
    }

    // "Image 1 is for Color: Gold" dropdowns
    private static function imageChoiceSection(): Section
    {
        $fields = [];

        foreach (range(1, ProductVariantManager::MAX_IMAGE_SLOTS) as $i) {
            $fields[] = Select::make('image_option_' . $i)
                ->label('Image ' . $i . ' is for')
                ->options(fn (Get $get): array => ProductVariantManager::bindingOptions((array) $get('options')))
                ->placeholder('Everyone (shown for every choice)')
                ->native(false)
                ->visible(fn (Get $get): bool => count((array) $get('image_paths')) >= $i);
        }

        return Section::make('Photos for a choice (optional)')
            ->description('"Image 1" is the first picture in the Images box above, "Image 2" the second, and so on. If a picture belongs to one choice (for example Color: Gold), customers see it when they pick that choice. Pictures left on "Everyone" always show.')
            ->visible(fn (Get $get): bool => (bool) $get('has_variants') && filled($get('options')))
            ->columns(2)
            ->columnSpanFull()
            ->schema($fields);
    }
}