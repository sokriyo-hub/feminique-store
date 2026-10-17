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
        'footer_address',
        'footer_phone',
        'facebook_url',
        'instagram_url',
        'free_delivery_threshold',
    ];

    public function mount(): void
    {
        $values = ['hero_images' => Setting::heroImages()];

        foreach (self::TEXT_KEYS as $key) {
            $values[$key] = Setting::get($key);
        }

        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
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
                                ->helperText('Up to 5 images, JPG, PNG or WebP, max 4 MB each. Use wide (landscape) high-quality images, at least 1600 px wide. One image = fixed hero. More than one = automatic slider (drag to change the order).'),
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

        $new = array_values(array_filter((array) ($data['hero_images'] ?? []), 'is_string'));
        $old = Setting::heroImages();

        // তালিকা থেকে বাদ পড়া ছবির ফাইল ডিস্ক থেকে মুছে যাবে
        foreach (array_diff($old, $new) as $removed) {
            Storage::disk('public')->delete($removed);
        }

        Setting::put('hero_images', $new ? json_encode($new, JSON_UNESCAPED_SLASHES) : null);
        Setting::put('hero_image', $new[0] ?? null);

        foreach (self::TEXT_KEYS as $key) {
            $value = $data[$key] ?? null;
            Setting::put($key, filled($value) ? trim((string) $value) : null);
        }

        Notification::make()
            ->success()
            ->title('Settings saved')
            ->send();
    }
}