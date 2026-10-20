<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Support\CategoryTree;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        $paths = CategoryTree::pathOptions();
        $lowStock = max(0, (int) Setting::get('low_stock_threshold', '5'));

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
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('category_id')
                    ->label('Category')
                    ->state(fn (Product $record): string => $paths[$record->category_id] ?? '-'),
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
                TextColumn::make('stock')
                    ->label('Stock')
                    ->state(fn (Product $record): int => $record->total_stock)
                    ->badge()
                    ->color(fn (int $state): string => $state <= 0 ? 'danger' : ($state <= $lowStock ? 'warning' : 'success'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options($paths)
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        $category = Category::find($data['value']);

                        return $query->whereIn('category_id', $category ? $category->branchIds() : []);
                    }),
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