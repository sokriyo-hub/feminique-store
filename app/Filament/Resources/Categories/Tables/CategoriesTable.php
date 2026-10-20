<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('parent'))
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('image')
                    ->label('Image')
                    ->disk('public')
                    ->square(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('Main category'),
                TextColumn::make('slug')
                    ->searchable(),
                IconColumn::make('show_in_navbar')
                    ->label('In navbar')
                    ->boolean(),
                TextColumn::make('children_count')
                    ->counts('children')
                    ->label('Sub-categories'),
                TextColumn::make('products_count')
                    ->counts('products')
                    ->label('Products'),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('top_level')
                    ->label('Main categories only')
                    ->query(fn ($query) => $query->whereNull('parent_id')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, Category $record) {
                        if ($record->children()->exists()) {
                            Notification::make()
                                ->title('Cannot delete this category')
                                ->body('It still has sub-categories. Move or delete those first.')
                                ->danger()
                                ->send();

                            $action->cancel();

                            return;
                        }

                        if ($record->products()->exists()) {
                            Notification::make()
                                ->title('Cannot delete this category')
                                ->body('It still has products. Move or delete those products first.')
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ]);
    }
}