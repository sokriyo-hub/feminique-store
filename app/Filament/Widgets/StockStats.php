<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Products\ProductResource;
use App\Support\StockReport;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StockStats extends StatsOverviewWidget
{
    protected static ?int $sort = -2;

    protected function getStats(): array
    {
        $threshold = StockReport::threshold();
        $low = StockReport::lowCount();
        $out = StockReport::outCount();
        $url = ProductResource::getUrl('index');

        return [
            Stat::make('Pieces in stock', number_format(StockReport::totalPieces()))
                ->description('All products and variants together')
                ->descriptionIcon(Heroicon::OutlinedArchiveBox)
                ->color('primary')
                ->url($url),
            Stat::make('Low stock', $low)
                ->description($low > 0 ? 'Products with ' . $threshold . ' or fewer pieces' : 'Nothing is running low')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($low > 0 ? 'warning' : 'success')
                ->url($url),
            Stat::make('Out of stock', $out)
                ->description($out > 0 ? 'Customers see "Sold out"' : 'Nothing is sold out')
                ->descriptionIcon(Heroicon::OutlinedXCircle)
                ->color($out > 0 ? 'danger' : 'success')
                ->url($url),
        ];
    }
}