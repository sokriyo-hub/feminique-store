<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use App\Support\CategoryTree;
use App\Support\ImageProcessor;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
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
                    ->helperText('English letters, numbers and dashes only. It is used in the web link. Every category (even a sub-category) needs its own unique slug, for example "gold-rings".'),
                Select::make('parent_id')
                    ->label('Parent category (optional)')
                    ->options(fn (?Category $record): array => CategoryTree::validParents($record?->id))
                    // The "+" button in the category list opens this page as /create?parent=ID
                    ->default(function (string $operation): ?int {
                        if ($operation !== 'create') {
                            return null;
                        }

                        $parent = request()->integer('parent');

                        return $parent > 0 && array_key_exists($parent, CategoryTree::validParents(null)) ? $parent : null;
                    })
                    ->placeholder('None (this is a main category)')
                    ->searchable()
                    ->live()
                    ->helperText('Leave empty for a main category. Choose a parent to make this a sub-category. Up to ' . Category::MAX_DEPTH . ' levels are allowed.')
                    ->rule(fn (?Category $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        if (blank($value)) {
                            return;
                        }

                        if (! array_key_exists((int) $value, CategoryTree::validParents($record?->id))) {
                            $fail('This parent is not allowed. A category cannot be inside itself, and only ' . Category::MAX_DEPTH . ' levels are allowed.');
                        }
                    }),
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
                    ->visible(fn (Get $get): bool => blank($get('parent_id')))
                    ->dehydratedWhenHidden()
                    ->dehydrateStateUsing(fn ($state, Get $get): bool => blank($get('parent_id')) ? (bool) $state : false)
                    ->helperText('Only main categories can be in the top menu (up to ' . self::NAVBAR_MAX . '). Their sub-categories appear in a drop-down. Every main category always stays on the home page and in the mobile menu.')
                    ->rule(fn (?Category $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        if (! $value) {
                            return;
                        }

                        $others = Category::where('show_in_navbar', true)
                            ->whereNull('parent_id')
                            ->when($record, fn ($query) => $query->where('id', '!=', $record->id))
                            ->count();

                        if ($others >= self::NAVBAR_MAX) {
                            $fail('Only ' . self::NAVBAR_MAX . ' categories can show in the navbar. Turn one off first.');
                        }
                    }),
                // Only main categories use sort_order. Sub-categories are always A-Z.
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(fn (): int => ((int) Category::whereNull('parent_id')->max('sort_order')) + 1)
                    ->visible(fn (Get $get): bool => blank($get('parent_id')))
                    ->helperText('Smaller number shows first. Easier way: drag the main categories in the category list. Sub-categories are always shown A-Z.'),
            ]);
    }
}