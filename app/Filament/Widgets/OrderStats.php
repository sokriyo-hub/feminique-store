<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\Product;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OrderStats extends StatsOverviewWidget
{
    protected static ?int $sort = -10;

    protected function getStats(): array
    {
        $pending = Order::where('status', 'pending')->count();

        return [
            Stat::make('Pending orders', $pending)
                ->description($pending > 0 ? 'Waiting for your confirmation' : 'No new orders')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color($pending > 0 ? 'warning' : 'success')
                ->url(OrderResource::getUrl('index')),
            Stat::make('Total orders', Order::count()),
            Stat::make('Total products', Product::count()),
        ];
    }
}