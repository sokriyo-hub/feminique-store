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

    // এই পেজে যে setting-গুলো আছে (hero_image আলাদা করে সামলানো হয়)
    private const TEXT_KEYS = [
        'footer_address',
        'footer_phone',
        'facebook_url',
        'instagram_url',
        'free_delivery_threshold',
    ];

    public function mount(): void
    {
        $values = ['hero_image' => Setting::get('hero_image')];

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
                    Section::make('Homepage hero image')
                        ->schema([
                            FileUpload::make('hero_image')
                                ->label('Hero image')
                                ->disk('public')
                                ->directory('hero')
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                ->maxSize(4096)
                                ->saveUploadedFileUsing(
                                    fn (TemporaryUploadedFile $file): string => ImageProcessor::storeAsWebp(
                                        $file->getRealPath(),
                                        'hero',
                                        errorField: 'data.hero_image',
                                        maxSide: ImageProcessor::HERO_MAX_SIDE,
                                        quality: ImageProcessor::HERO_QUALITY,
                                    )
                                )
                                ->helperText('JPG, PNG or WebP, max 4 MB. Use a wide (landscape) high-quality image, at least 1600 px wide. It is converted to WebP automatically (up to 2000 px wide).'),
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

        // hero image: নতুন ছবি দিলে পুরোনো ফাইল ডিস্ক থেকে মুছে যাবে
        $newHero = $data['hero_image'] ?? null;
        if (is_array($newHero)) {
            $newHero = array_values($newHero)[0] ?? null;
        }

        $oldHero = Setting::get('hero_image');

        if ($oldHero && $oldHero !== $newHero) {
            Storage::disk('public')->delete($oldHero);
        }

        Setting::put('hero_image', $newHero ?: null);

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