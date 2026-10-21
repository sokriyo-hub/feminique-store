<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\ImageProcessor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * @property-read Schema $form
 */
class ManageSettings extends Page
{
    protected string $view = 'filament.pages.manage-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Settings';

    protected static ?string $slug = 'settings';

    protected static ?int $navigationSort = 90;

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    private const TEXT_KEYS = [
        'top_strip_text',
        'footer_address',
        'footer_phone',
        'facebook_url',
        'instagram_url',
        'free_delivery_threshold',
        'low_stock_threshold',
    ];

    // Text shown in the top strip until the admin saves their own
    private const DEFAULT_TOP_STRIP = 'Cash on Delivery available';

    // Low-stock limit used until the admin saves their own
    private const DEFAULT_LOW_STOCK = '5';

    public function mount(): void
    {
        $values = [
            'hero_images' => Setting::heroImages(),
            'site_logo' => Setting::get('site_logo'),
        ];

        foreach (self::TEXT_KEYS as $key) {
            $values[$key] = Setting::get($key);
        }

        // Never saved yet: show the default text. Saved as empty: keep it empty (strip hidden).
        $values['top_strip_text'] = Setting::where('name', 'top_strip_text')->exists()
            ? Setting::get('top_strip_text')
            : self::DEFAULT_TOP_STRIP;

        $values['low_stock_threshold'] = Setting::get('low_stock_threshold', self::DEFAULT_LOW_STOCK);

        foreach (array_keys(Setting::HERO_DEFAULTS) as $key) {
            $values[$key] = Setting::valueOrDefault($key);
        }

        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('Logo')
                        ->schema([
                            FileUpload::make('site_logo')
                                ->label('Brand logo')
                                ->disk('public')
                                ->directory('branding')
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                ->maxSize(2048)
                                ->saveUploadedFileUsing(
                                    fn (TemporaryUploadedFile $file): string => ImageProcessor::storeAsWebp(
                                        $file->getRealPath(),
                                        'branding',
                                        errorField: 'data.site_logo',
                                        maxSide: 1200,
                                        quality: 90,
                                    )
                                )
                                ->helperText('Shown at the left of the menu. Use a PNG with a transparent background, wide (horizontal) logo works best, max 2 MB. If empty, the text "Feminique" is shown.'),
                        ]),
                    Section::make('Homepage hero images')
                        ->schema([
                            FileUpload::make('hero_images')
                                ->label('Hero images')
                                ->multiple()
                                ->reorderable()
                                ->appendFiles()
                                ->maxFiles(5)
                                ->panelLayout('grid')
                                ->disk('public')
                                ->directory('hero')
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                ->maxSize(4096)
                                ->saveUploadedFileUsing(
                                    fn (TemporaryUploadedFile $file): string => ImageProcessor::storeAsWebp(
                                        $file->getRealPath(),
                                        'hero',
                                        errorField: 'data.hero_images',
                                        maxSide: ImageProcessor::HERO_MAX_SIDE,
                                        quality: ImageProcessor::HERO_QUALITY,
                                    )
                                )
                                ->helperText('Up to 5 images, JPG, PNG or WebP, max 4 MB each. Use wide (landscape), LIGHT-coloured, high-quality images, at least 1600 px wide, with free space on the left side for the text. One image = fixed hero. More than one = automatic slider (drag to change the order).'),
                        ]),
                    Section::make('Homepage hero text')
                        ->description('Shown on top of the hero images. Leave a field empty to hide it.')
                        ->schema([
                            TextInput::make('hero_eyebrow')
                                ->label('Small line above the heading')
                                ->maxLength(80),
                            Textarea::make('hero_title')
                                ->label('Heading')
                                ->rows(2)
                                ->maxLength(80)
                                ->helperText('Press Enter to start a new line.'),
                            Textarea::make('hero_text')
                                ->label('Short description')
                                ->rows(3)
                                ->maxLength(200)
                                ->helperText('Press Enter to start a new line.'),
                            TextInput::make('hero_badge_1')
                                ->label('Badge 1 (diamond icon)')
                                ->maxLength(40),
                            TextInput::make('hero_badge_2')
                                ->label('Badge 2 (truck icon)')
                                ->maxLength(40),
                            TextInput::make('hero_badge_3')
                                ->label('Badge 3 (heart icon)')
                                ->maxLength(40),
                        ]),
                    Section::make('Top announcement bar')
                        ->schema([
                            TextInput::make('top_strip_text')
                                ->label('Top announcement text')
                                ->maxLength(120)
                                ->placeholder('Cash on Delivery available')
                                ->helperText('Shown in the thin bar above the menu on every page. Leave empty to hide the bar.'),
                        ]),
                    Section::make('Footer')
                        ->schema([
                            Textarea::make('footer_address')
                                ->label('Address')
                                ->rows(2)
                                ->maxLength(500),
                            TextInput::make('footer_phone')
                                ->label('Phone')
                                ->tel()
                                ->maxLength(30),
                            TextInput::make('facebook_url')
                                ->label('Facebook link')
                                ->url()
                                ->maxLength(255)
                                ->placeholder('https://facebook.com/yourpage'),
                            TextInput::make('instagram_url')
                                ->label('Instagram link')
                                ->url()
                                ->maxLength(255)
                                ->placeholder('https://instagram.com/yourpage'),
                        ]),
                    Section::make('Delivery')
                        ->schema([
                            TextInput::make('free_delivery_threshold')
                                ->label('Free delivery above (Tk)')
                                ->numeric()
                                ->minValue(0)
                                ->prefix('Tk')
                                ->helperText('Orders at or above this amount get free delivery. Leave empty to turn this off.'),
                        ]),
                    Section::make('Stock alerts')
                        ->schema([
                            TextInput::make('low_stock_threshold')
                                ->label('Low stock limit (pieces)')
                                ->numeric()
                                ->integer()
                                ->minValue(0)
                                ->maxValue(1000)
                                ->placeholder('5')
                                ->helperText('A product with this many pieces or fewer is shown as "low stock" on the dashboard and in the Products list. Leave empty to use 5.'),
                        ]),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Save settings')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Hero images removed from the list are deleted from disk
        $new = array_values(array_filter((array) ($data['hero_images'] ?? []), 'is_string'));
        $old = Setting::heroImages();

        foreach (array_diff($old, $new) as $removed) {
            Storage::disk('public')->delete($removed);
        }

        Setting::put('hero_images', $new ? json_encode($new, JSON_UNESCAPED_SLASHES) : null);
        Setting::put('hero_image', $new[0] ?? null);

        // Logo: a replaced or removed logo is deleted from disk
        $logo = $data['site_logo'] ?? null;

        if (is_array($logo)) {
            $logo = array_values($logo)[0] ?? null;
        }

        $oldLogo = Setting::get('site_logo');

        if ($oldLogo && $oldLogo !== $logo) {
            Storage::disk('public')->delete($oldLogo);
        }

        Setting::put('site_logo', is_string($logo) && $logo !== '' ? $logo : null);

        foreach (array_merge(self::TEXT_KEYS, array_keys(Setting::HERO_DEFAULTS)) as $key) {
            $value = $data[$key] ?? null;
            Setting::put($key, filled($value) ? trim((string) $value) : null);
        }

        Notification::make()
            ->success()
            ->title('Settings saved')
            ->send();
    }
}