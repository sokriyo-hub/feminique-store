<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('images'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('main_image')
                    ->label('Image')
                    ->disk('public')
                    ->square()
                    ->state(fn (Product $record) => $record->images->first()?->path),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Regular price')
                    ->money('BDT')
                    ->sortable(),
                TextColumn::make('discount_price')
                    ->label('Discounted price')
                    ->money('BDT')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('discount_percent')
                    ->label('Discount')
                    ->state(fn (Product $record) => $record->discount_percent > 0 ? $record->discount_percent . '%' : '-'),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->relationship('category', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}