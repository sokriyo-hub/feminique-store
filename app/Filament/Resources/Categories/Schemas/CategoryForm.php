<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use App\Support\ImageProcessor;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CategoryForm
{
    public const NAVBAR_MAX = 6;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                FileUpload::make('image')
                    ->label('Category image (optional)')
                    ->disk('public')
                    ->directory('categories')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(2048)
                    ->saveUploadedFileUsing(
                        fn (TemporaryUploadedFile $file): string => ImageProcessor::storeAsWebp(
                            $file->getRealPath(),
                            'categories',
                            errorField: 'data.image',
                            maxSide: 1200,
                        )
                    )
                    ->helperText('JPG, PNG or WebP, max 2 MB. A portrait (tall) image works best. Converted to WebP automatically.'),
                Toggle::make('show_in_navbar')
                    ->label('Show in navbar')
                    ->default(false)
                    ->helperText('Up to ' . self::NAVBAR_MAX . ' categories can show in the top menu. Every category always stays on the home page and in the mobile menu.')
                    ->rule(fn (?Category $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        if (! $value) {
                            return;
                        }

                        $others = Category::where('show_in_navbar', true)
                            ->when($record, fn ($query) => $query->where('id', '!=', $record->id))
                            ->count();

                        if ($others >= self::NAVBAR_MAX) {
                            $fail('Only ' . self::NAVBAR_MAX . ' categories can show in the navbar. Turn one off first.');
                        }
                    }),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->helperText('Smaller number shows first. You can also drag rows in the list.'),
            ]);
    }
}