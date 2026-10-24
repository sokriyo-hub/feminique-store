<?php

namespace App\Filament\Resources\Ads\Schemas;

use App\Models\Ad;
use App\Support\AdSlots;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AdForm
{
    public const MAX_VIDEO_BYTES = 8 * 1024 * 1024;

    public const MAX_GIF_BYTES = 3 * 1024 * 1024;

    // File type brands of a real MP4 (the 4 letters after "ftyp")
    private const MP4_BRANDS = ['isom', 'iso2', 'mp41', 'mp42', 'avc1', 'M4V ', 'dash'];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ad')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Name (only you see this)')
                            ->required()
                            ->maxLength(100)
                            ->columnSpanFull(),
                        CheckboxList::make('placements')
                            ->label('Where to show this ad')
                            ->options(fn (): array => AdSlots::options())
                            ->disableOptionWhen(fn (string $value, ?Ad $record): bool => array_key_exists($value, AdSlots::taken('ad', $record?->id)))
                            ->descriptions(fn (?Ad $record): array => array_map(
                                fn (string $by): string => 'Already used by ' . $by,
                                AdSlots::taken('ad', $record?->id)
                            ))
                            ->columns(2)
                            ->required()
                            ->minItems(1)
                            ->rule(function (?Ad $record, Get $get): Closure {
                                $needsFreePlaces = AdSlots::occupies((bool) $get('is_active'), $get('ends_at'));
                                $id = $record?->id;

                                return function (string $attribute, mixed $value, Closure $fail) use ($needsFreePlaces, $id): void {
                                    if (! $needsFreePlaces) {
                                        return;
                                    }

                                    $taken = AdSlots::taken('ad', $id);

                                    foreach ((array) $value as $slot) {
                                        if (isset($taken[$slot])) {
                                            $fail(AdSlots::label($slot) . ' is already used by ' . $taken[$slot] . '. Choose another place, or turn that one off first.');

                                            return;
                                        }
                                    }
                                };
                            })
                            ->helperText('Tick one or more places. A place holds only ONE ad or banner at a time: places that are already used are greyed out and show what uses them. To use such a place, turn that ad or banner off (or delete it) first. You can also switch a whole place on or off in "Ad places".')
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->live(),
                        DateTimePicker::make('starts_at')
                            ->label('Starts at (optional)')
                            ->seconds(false),
                        DateTimePicker::make('ends_at')
                            ->label('Ends at (optional)')
                            ->seconds(false)
                            ->live(onBlur: true)
                            ->after('starts_at')
                            ->helperText('Leave both dates empty to show the ad until you turn it off.'),
                    ]),

                Section::make('Picture or video')
                    ->description('Every ad has the same height as a product picture, so you only upload the file. Best desktop size: 2000 x 420 px (about 4.8 : 1). Keep the important part in the middle 60%, the edges can be cropped.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        FileUpload::make('media')
                            ->label('Picture, GIF or video')
                            ->required()
                            ->disk('public')
                            ->directory('ads')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'video/mp4'])
                            ->maxSize(8192)
                            ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => self::storeMedia($file))
                            ->helperText('JPG, PNG or WebP (made smaller automatically), GIF up to 3 MB, MP4 video up to 8 MB. A video plays by itself, without sound, and repeats. A short video is lighter and sharper than a GIF.')
                            ->columnSpanFull(),
                        FileUpload::make('poster')
                            ->label('Video cover picture (needed for a video)')
                            ->required(fn (Get $get): bool => self::mediaKind($get('media')) === 'video')
                            ->disk('public')
                            ->directory('ads')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(4096)
                            ->saveUploadedFileUsing(
                                fn (TemporaryUploadedFile $file): string => ImageProcessor::storeAsWebp(
                                    $file->getRealPath(),
                                    'ads',
                                    errorField: 'data.poster',
                                    maxSide: 1600,
                                )
                            )
                            ->helperText('Shown while the video loads. Same size as the main picture (2000 x 420 px).'),
                        FileUpload::make('mobile_media')
                            ->label('Phone picture (optional)')
                            ->disk('public')
                            ->directory('ads')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(4096)
                            ->saveUploadedFileUsing(
                                fn (TemporaryUploadedFile $file): string => ImageProcessor::storeAsWebp(
                                    $file->getRealPath(),
                                    'ads',
                                    errorField: 'data.mobile_media',
                                    maxSide: 1400,
                                )
                            )
                            ->helperText('Best size: 1000 x 600 px (about 1.7 : 1). Without it, phones show the middle part of the main picture. Not used for videos.'),
                    ]),

                Section::make('Text on the ad (optional)')
                    ->description('Leave the title, text and button empty to show only the picture. If you add a link, the whole ad can be tapped.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('title')
                            ->maxLength(120)
                            ->columnSpanFull(),
                        Textarea::make('text')
                            ->rows(3)
                            ->maxLength(300)
                            ->columnSpanFull(),
                        TextInput::make('button_text')
                            ->label('Button text')
                            ->maxLength(40)
                            ->placeholder('Shop now'),
                        TextInput::make('link_url')
                            ->label('Link')
                            ->maxLength(500)
                            ->placeholder('https://... or /category/rings')
                            ->required(fn (Get $get): bool => filled($get('button_text')))
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? trim((string) $state) : null)
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (filled($value) && ! Ad::isSafeLink(trim((string) $value))) {
                                    $fail('Start the link with https://, http:// or / (a page of this site).');
                                }
                            })
                            ->helperText('Needed when there is a button. Use / for a page of this site, for example /category/rings.'),
                        Select::make('text_color')
                            ->label('Text colour')
                            ->options([
                                'light' => 'White text (for dark pictures)',
                                'dark' => 'Dark text (for light pictures)',
                            ])
                            ->default('light')
                            ->required()
                            ->native(false),
                        Select::make('text_align')
                            ->label('Text position')
                            ->options([
                                'left' => 'Left',
                                'center' => 'Middle',
                                'right' => 'Right',
                            ])
                            ->default('left')
                            ->required()
                            ->native(false),
                    ]),
            ]);
    }

    // image | gif | video from the file in the form (a new upload or an already saved path)
    public static function mediaKind(mixed $state): ?string
    {
        $value = is_array($state) ? (reset($state) ?: null) : $state;

        if ($value instanceof TemporaryUploadedFile) {
            $extension = strtolower($value->getClientOriginalExtension());
        } elseif (is_string($value) && $value !== '') {
            $extension = strtolower(pathinfo($value, PATHINFO_EXTENSION));
        } else {
            return null;
        }

        return match ($extension) {
            'mp4' => 'video',
            'gif' => 'gif',
            default => 'image',
        };
    }

    // Checks the REAL file (not just its name), then saves it
    private static function storeMedia(TemporaryUploadedFile $file): string
    {
        $path = $file->getRealPath();
        $head = (string) @file_get_contents($path, false, null, 0, 12);

        // MP4 video
        if (strlen($head) >= 12 && substr($head, 4, 4) === 'ftyp') {
            if (! in_array(substr($head, 8, 4), self::MP4_BRANDS, true)) {
                throw ValidationException::withMessages(['data.media' => 'Only MP4 video is allowed.']);
            }

            if ((int) $file->getSize() > self::MAX_VIDEO_BYTES) {
                throw ValidationException::withMessages(['data.media' => 'This video is too large (max 8 MB). Please make it shorter or smaller and try again.']);
            }

            return (string) Storage::disk('public')->putFileAs('ads', $file, Str::uuid() . '.mp4');
        }

        // GIF (kept as it is, so it stays animated)
        $info = @getimagesize($path);

        if ($info !== false && $info[2] === IMAGETYPE_GIF) {
            if ((int) $file->getSize() > self::MAX_GIF_BYTES) {
                throw ValidationException::withMessages(['data.media' => 'This GIF is too large (max 3 MB). A short MP4 video is lighter and looks better.']);
            }

            return (string) Storage::disk('public')->putFileAs('ads', $file, Str::uuid() . '.gif');
        }

        // JPG / PNG / WebP: checked, made smaller, saved as WebP
        return ImageProcessor::storeAsWebp(
            $path,
            'ads',
            errorField: 'data.media',
            maxSide: ImageProcessor::HERO_MAX_SIDE,
            quality: ImageProcessor::HERO_QUALITY,
        );
    }
}