<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Support\StockReport;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class StockAlerts extends TableWidget
{
    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $total = StockReport::totalSql();
        $threshold = StockReport::threshold();

        return $table
            ->heading('Low and out-of-stock products')
            ->description('Showing products with ' . $threshold . ' or fewer pieces, emptiest first. Change the limit in Settings.')
            ->query(
                Product::query()
                    ->select('products.*')
                    ->selectRaw($total . ' as stock_now')
                    ->whereRaw($total . ' <= ?', [$threshold])
                    ->orderByRaw($total . ' asc')
                    ->orderBy('products.name')
            )
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->recordUrl(fn (Product $record): string => ProductResource::getUrl('edit', ['record' => $record]))
            ->emptyStateHeading('All products have enough stock')
            ->columns([
                TextColumn::make('name')
                    ->label('Product'),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->placeholder('-'),
                TextColumn::make('stock_now')
                    ->label('Pieces left')
                    ->badge()
                    ->color(fn ($state): string => (int) $state <= 0 ? 'danger' : 'warning'),
            ]);
    }
}