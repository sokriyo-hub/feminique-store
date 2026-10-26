<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\AdminOrderCreator;
use App\Support\Phone;
use App\Support\ReturnGuard;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * @property-read Schema $form
 */
class OrderDesk extends Page
{
    protected string $view = 'filament.pages.order-desk';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?string $navigationLabel = 'Order desk';

    protected static ?string $title = 'Order desk';

    protected static ?string $slug = 'order-desk';

    protected static ?int $navigationSort = 11;

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'source' => 'phone',
            'status' => 'confirmed',
            'discount' => 0,
            'zone_id' => DeliveryZone::where('is_active', true)->orderBy('id')->value('id'),
            'lines' => [['quantity' => 1]],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('Customer')
                        ->description('For orders that come by Facebook, Instagram, WhatsApp, phone or in the shop. Give the customer the order code and mobile number: they can track the order on the website.')
                        ->columns(2)
                        ->schema([
                            TextInput::make('name')
                                ->label('Customer name')
                                ->required()
                                ->minLength(2)
                                ->maxLength(100),
                            TextInput::make('phone')
                                ->label('Mobile number')
                                ->tel()
                                ->required()
                                ->maxLength(20)
                                ->placeholder('01XXXXXXXXX')
                                ->live(onBlur: true)
                                ->hint(fn (Get $get): ?string => ReturnGuard::warningFor((string) $get('phone')))
                                ->hintColor('danger')
                                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    if (Phone::normalize((string) $value) === null) {
                                        $fail('Enter a valid mobile number, like 01712345678.');
                                    }
                                }),
                            Textarea::make('address')
                                ->label('Delivery address')
                                ->required()
                                ->minLength(5)
                                ->maxLength(500)
                                ->rows(2)
                                ->columnSpanFull(),
                            TextInput::make('email')
                                ->label('Email (optional)')
                                ->email()
                                ->maxLength(255),
                        ]),

                    Section::make('Order')
                        ->columns(2)
                        ->schema([
                            Select::make('source')
                                ->label('Order source')
                                ->options(Order::deskSourceOptions())
                                ->required()
                                ->native(false)
                                ->helperText('Where did this order come from?'),
                            Select::make('status')
                                ->label('Order status')
                                ->options(Order::deskStatusOptions())
                                ->required()
                                ->native(false)
                                ->helperText('Stock is taken from the shelf as soon as the order is saved. Cancel the order later to put it back.'),
                        ]),

                    Section::make('Items')
                        ->schema([
                            Repeater::make('lines')
                                ->hiddenLabel()
                                ->schema([
                                    Select::make('product_id')
                                        ->label('Product')
                                        ->searchable()
                                        ->required()
                                        ->live()
                                        ->placeholder('Type a product name or SKU')
                                        ->getSearchResultsUsing(fn (string $search): array => self::searchProducts($search))
                                        ->getOptionLabelUsing(fn ($value): ?string => self::productLabel((int) $value))
                                        ->afterStateUpdated(function (Get $get, Set $set): void {
                                            $set('variant_id', null);
                                            self::fillPrice($get, $set);
                                        })
                                        ->columnSpan(['default' => 2, 'lg' => 3]),
                                    Select::make('variant_id')
                                        ->label('Variant')
                                        ->native(false)
                                        ->options(fn (Get $get): array => self::variantOptions((int) $get('product_id')))
                                        ->visible(fn (Get $get): bool => self::hasVariants((int) $get('product_id')))
                                        ->required(fn (Get $get): bool => self::hasVariants((int) $get('product_id')))
                                        ->live()
                                        ->afterStateUpdated(fn (Get $get, Set $set) => self::fillPrice($get, $set))
                                        ->columnSpan(['default' => 2, 'lg' => 3]),
                                    TextInput::make('quantity')
                                        ->label('Quantity')
                                        ->required()
                                        ->numeric()
                                        ->integer()
                                        ->minValue(1)
                                        ->maxValue(AdminOrderCreator::MAX_QTY)
                                        ->default(1)
                                        ->columnSpan(['default' => 1, 'lg' => 2]),
                                    TextInput::make('unit_price')
                                        ->label('Unit price')
                                        ->required()
                                        ->numeric()
                                        ->minValue(0)
                                        ->prefix('Tk')
                                        ->helperText('Filled automatically with today\'s selling price (campaign included). You can change it.')
                                        ->columnSpan(['default' => 1, 'lg' => 2]),
                                ])
                                ->columns(['default' => 2, 'lg' => 6])
                                ->minItems(1)
                                ->defaultItems(0)
                                ->addActionLabel('Add another product')
                                ->helperText('The product list shows how many pieces are in stock.'),
                        ]),

                    Section::make('Delivery and discount')
                        ->columns(3)
                        ->schema([
                            Select::make('zone_id')
                                ->label('Delivery zone')
                                ->options(fn (): array => DeliveryZone::where('is_active', true)->orderBy('id')->pluck('name', 'id')->all())
                                ->required()
                                ->native(false),
                            TextInput::make('custom_charge')
                                ->label('Custom delivery charge (optional)')
                                ->numeric()
                                ->minValue(0)
                                ->prefix('Tk')
                                ->helperText('Empty = the zone charge (free-delivery rules apply). Type 0 for free delivery.'),
                            TextInput::make('discount')
                                ->label('Discount on the whole order')
                                ->numeric()
                                ->minValue(0)
                                ->default(0)
                                ->prefix('Tk')
                                ->helperText('Taken off the items total.'),
                        ]),
                ])
                    ->livewireSubmitHandler('create')
                    ->footer([
                        Actions::make([
                            Action::make('create')
                                ->label('Create order')
                                ->submit('create')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function create(): void
    {
        $data = $this->form->getState();

        [$order, $error] = AdminOrderCreator::create([
            'name' => $data['name'] ?? '',
            'phone' => $data['phone'] ?? '',
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? '',
            'source' => $data['source'] ?? 'other',
            'status' => $data['status'] ?? 'confirmed',
            'zone_id' => $data['zone_id'] ?? null,
            'custom_charge' => $data['custom_charge'] ?? null,
            'discount' => $data['discount'] ?? 0,
            'lines' => array_values((array) ($data['lines'] ?? [])),
        ]);

        if ($error !== null) {
            Notification::make()
                ->danger()
                ->title('Order was not created')
                ->body($error)
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Order created')
            ->body('Order code: ' . $order->code . '. The customer can track it with this code and the mobile number.')
            ->persistent()
            ->send();
        $warning = ReturnGuard::warningFor($order->phone);

        if ($warning !== null) {
            Notification::make()->warning()->title('Check this customer')->body($warning)->persistent()->send();
        }
        $this->redirect(OrderResource::getUrl('view', ['record' => $order]));
    }

    // ---------- small helpers for the product / variant fields ----------

    private static function searchProducts(string $search): array
    {
        $like = '%' . addcslashes($search, '\\%_') . '%';

        return Product::query()
            ->withSum('variants', 'stock')
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('sku', 'like', $like))
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Product $product): array => [$product->id => $product->name . ' - stock ' . $product->total_stock])
            ->all();
    }

    private static function productLabel(int $id): ?string
    {
        $product = Product::withSum('variants', 'stock')->find($id);

        return $product ? $product->name . ' - stock ' . $product->total_stock : null;
    }

    private static function hasVariants(int $productId): bool
    {
        return $productId > 0 && (bool) Product::whereKey($productId)->value('has_variants');
    }

    private static function variantOptions(int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }

        return ProductVariant::where('product_id', $productId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (ProductVariant $variant): array => [$variant->id => $variant->label() . ' (stock ' . $variant->stock . ')'])
            ->all();
    }

    // Fills "Unit price" with the live selling price of the chosen product / variant
    private static function fillPrice(Get $get, Set $set): void
    {
        $product = Product::with('variants')->find((int) $get('product_id'));

        if (! $product) {
            $set('unit_price', null);

            return;
        }

        $variant = null;

        if ($product->has_variants) {
            $variant = $product->variants->firstWhere('id', (int) $get('variant_id'));

            if (! $variant) {
                $set('unit_price', null);

                return;
            }
        }

        [, $selling] = $product->linePrices($variant);

        $set('unit_price', $selling);
    }
}