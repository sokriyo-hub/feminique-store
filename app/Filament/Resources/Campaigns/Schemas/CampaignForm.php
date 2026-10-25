<?php

namespace App\Filament\Resources\Campaigns\Schemas;

use App\Models\Ad;
use App\Models\Campaign;
use App\Models\Product;
use App\Support\AdSlots;
use App\Support\CategoryTree;
use App\Support\ImageProcessor;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CampaignForm
{
    public const MAX_PRODUCTS = 500;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Campaign')
                    ->description('A campaign lowers prices by a percentage between its start and end time. It starts and stops by itself. If a product already has a better discount, the customer gets that one (discounts are never added together).')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Campaign name')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('11.11 Sale')
                            ->columnSpanFull(),
                        TextInput::make('discount_percent')
                            ->label('Discount')
                            ->required()
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(90)
                            ->suffix('%')
                            ->helperText('Taken from the regular price, rounded to whole taka.'),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Turn off to stop the campaign at once, even inside its dates.'),
                        DateTimePicker::make('starts_at')
                            ->label('Starts at')
                            ->required()
                            ->seconds(false),
                        DateTimePicker::make('ends_at')
                            ->label('Ends at')
                            ->required()
                            ->seconds(false)
                            ->after('starts_at'),
                        Toggle::make('all_products')
                            ->label('All products')
                            ->default(true)
                            ->live()
                            ->helperText('Turn off to choose categories and/or single products. You can choose both: the discount then covers every product in the chosen categories (and their sub-categories) plus the chosen products.')
                            ->columnSpanFull(),
                        Select::make('category_ids')
                            ->label('Categories in this campaign')
                            ->multiple()
                            ->searchable()
                            ->live()
                            ->options(fn (): array => CategoryTree::pathOptions())
                            ->visible(fn (Get $get): bool => ! $get('all_products'))
                            ->required(fn (Get $get): bool => ! $get('all_products') && blank($get('product_ids')))
                            ->helperText('A chosen category includes all its sub-categories.')
                            ->columnSpanFull(),
                        Select::make('product_ids')
                            ->label('Single products in this campaign')
                            ->multiple()
                            ->searchable()
                            ->live()
                            ->maxItems(self::MAX_PRODUCTS)
                            ->getSearchResultsUsing(function (string $search): array {
                                $like = '%' . addcslashes($search, '\\%_') . '%';

                                return Product::query()
                                    ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('sku', 'like', $like))
                                    ->orderBy('name')
                                    ->limit(50)
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->getOptionLabelsUsing(fn (array $values): array => Product::query()
                                ->whereIn('id', $values)
                                ->pluck('name', 'id')
                                ->all())
                            ->visible(fn (Get $get): bool => ! $get('all_products'))
                            ->required(fn (Get $get): bool => ! $get('all_products') && blank($get('category_ids')))
                            ->placeholder('Type a product name or SKU')
                            ->helperText('Up to ' . self::MAX_PRODUCTS . ' products, from any categories. Type to search. Customers see all of them together on the offer page of this campaign.')
                            ->columnSpanFull(),
                        Toggle::make('free_delivery')
                            ->label('Free delivery during this campaign')
                            ->default(false)
                            ->helperText('Applies to EVERY order placed while the campaign runs, even if the discount covers only some products.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Offer page')
                    ->description('Every campaign gets its own page that lists only the products this discount covers. The banner button opens it. You can also add a link to it in the top menu.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('offer_in_menu')
                            ->label('Show in the top menu')
                            ->default(false)
                            ->helperText('Only while the campaign is running. If several campaigns have this on, the one with the biggest discount is shown.'),
                        TextInput::make('offer_title')
                            ->label('Offer title (catchy)')
                            ->maxLength(60)
                            ->placeholder('Festive Offers')
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? trim((string) $state) : null)
                            ->helperText('Used as the menu name and the heading of the offer page. If empty: the menu says "Offers" and the heading is the campaign name.'),
                        TextInput::make('offer_link')
                            ->label('Offer page link')
                            ->readOnly()
                            ->dehydrated(false)
                            ->visible(fn (?Campaign $record): bool => filled($record?->slug))
                            ->helperText('The page of this campaign. You can use this link in an ad or anywhere else. It does not change when you rename the campaign.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Banner')
                    ->description('Shown while the campaign is running. A place can hold only one ad or banner at a time: places that are already used are greyed out and show what uses them. Leave the text fields empty to hide them.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('show_banner')
                            ->label('Show banner')
                            ->default(true)
                            ->live(),
                        Toggle::make('show_countdown')
                            ->label('Show countdown clock')
                            ->default(true),
                        CheckboxList::make('banner_slots')
                            ->label('Where to show the banner')
                            ->options(fn (): array => AdSlots::options())
                            ->default(fn (): array => array_key_exists(AdSlots::DEFAULT_CAMPAIGN_SLOT, AdSlots::taken('campaign', null))
                                ? []
                                : [AdSlots::DEFAULT_CAMPAIGN_SLOT])
                            ->disableOptionWhen(fn (string $value, ?Campaign $record): bool => array_key_exists($value, AdSlots::taken('campaign', $record?->id)))
                            ->descriptions(fn (?Campaign $record): array => array_map(
                                fn (string $by): string => 'Already used by ' . $by,
                                AdSlots::taken('campaign', $record?->id)
                            ))
                            ->columns(2)
                            ->required(fn (Get $get): bool => (bool) $get('show_banner'))
                            ->rule(function (?Campaign $record, Get $get): Closure {
                                $needsFreePlaces = (bool) $get('show_banner') && AdSlots::occupies((bool) $get('is_active'), $get('ends_at'));
                                $id = $record?->id;

                                return function (string $attribute, mixed $value, Closure $fail) use ($needsFreePlaces, $id): void {
                                    if (! $needsFreePlaces) {
                                        return;
                                    }

                                    $taken = AdSlots::taken('campaign', $id);

                                    foreach ((array) $value as $slot) {
                                        if (isset($taken[$slot])) {
                                            $fail(AdSlots::label($slot) . ' is already used by ' . $taken[$slot] . '. Choose another place.');

                                            return;
                                        }
                                    }
                                };
                            })
                            ->helperText('Tick one or more places. A place that is turned off in "Ad places" shows nothing.')
                            ->columnSpanFull(),
                        TextInput::make('banner_title')
                            ->label('Banner title')
                            ->maxLength(120)
                            ->helperText('If empty, the campaign name is used.')
                            ->columnSpanFull(),
                        Textarea::make('banner_text')
                            ->label('Banner text')
                            ->rows(3)
                            ->maxLength(300)
                            ->helperText('On phones the text is hidden to keep the banner small.')
                            ->columnSpanFull(),
                        TextInput::make('banner_button')
                            ->label('Button text')
                            ->maxLength(40)
                            ->placeholder('Shop now')
                            ->helperText('Leave empty for no button.')
                            ->columnSpanFull(),
                        TextInput::make('banner_link')
                            ->label('Button link (optional)')
                            ->maxLength(255)
                            ->placeholder('/category/rings   or   https://...')
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? trim((string) $state) : null)
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (filled($value) && ! Ad::isSafeLink(trim((string) $value))) {
                                    $fail('Use a full link starting with https:// or http://, or a page of this site starting with / (for example /category/rings).');
                                }
                            })
                            ->helperText('Where the button goes. Leave empty to open the offer page of this campaign.')
                            ->columnSpanFull(),
                        FileUpload::make('banner_image')
                            ->label('Banner image (optional)')
                            ->disk('public')
                            ->directory('campaigns')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(4096)
                            ->saveUploadedFileUsing(
                                fn (TemporaryUploadedFile $file): string => ImageProcessor::storeAsWebp(
                                    $file->getRealPath(),
                                    'campaigns',
                                    errorField: 'data.banner_image',
                                    maxSide: ImageProcessor::HERO_MAX_SIDE,
                                    quality: ImageProcessor::HERO_QUALITY,
                                )
                            )
                            ->helperText('Best size: 2000 x 420 px (about 4.8 : 1). Keep the important part in the middle 60%, the edges can be cropped. JPG, PNG or WebP, max 4 MB. A dark overlay is added so the white text stays readable. Without an image the banner is plain Deep Cocoa.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Takes the chosen categories and products out of the form data (they are not campaign columns).
     * Returns [categoryIds, productIds]. "All products" on = both empty.
     */
    public static function takeTargets(array &$data): array
    {
        $all = (bool) ($data['all_products'] ?? false);

        $categories = array_values(array_unique(array_map('intval', (array) ($data['category_ids'] ?? []))));
        $products = array_values(array_unique(array_map('intval', (array) ($data['product_ids'] ?? []))));

        unset($data['category_ids'], $data['product_ids']);

        return $all ? [[], []] : [$categories, $products];
    }
}