<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StoreStats extends StatsOverviewWidget
{
    protected static ?int $sort = -9;

    protected function getStats(): array
    {
        // Sales = every order except cancelled
        $monthStart = now()->startOfMonth();
        $lastMonthStart = now()->subMonthNoOverflow()->startOfMonth();

        $current = Order::where('status', '!=', 'cancelled')->where('created_at', '>=', $monthStart);
        $currentSales = (float) (clone $current)->sum('total');
        $currentOrders = (clone $current)->count();

        $lastSales = (float) Order::where('status', '!=', 'cancelled')
            ->where('created_at', '>=', $lastMonthStart)
            ->where('created_at', '<', $monthStart)
            ->sum('total');

        $change = $lastSales > 0 ? (int) round(($currentSales - $lastSales) / $lastSales * 100) : null;
        $today = Order::where('created_at', '>=', now()->startOfDay())->count();

        $monthDescription = ($change !== null
            ? ($change >= 0 ? '+' : '') . $change . '% vs last month'
            : $currentOrders . ' orders')
            . ' · Today: ' . $today;

        $average = (float) Order::where('status', '!=', 'cancelled')->avg('total');

        $customers = Order::distinct()->count('phone');
        $returning = Order::query()
            ->select('phone')
            ->groupBy('phone')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        $onSale = Product::whereNotNull('discount_price')->whereColumn('discount_price', '<', 'price')->count();

        return [
            Stat::make('Sales this month', Money::format($currentSales))
                ->description($monthDescription)
                ->descriptionIcon($change !== null && $change < 0
                    ? Heroicon::OutlinedArrowTrendingDown
                    : Heroicon::OutlinedArrowTrendingUp)
                ->color($change !== null && $change < 0 ? 'danger' : 'success'),
            Stat::make('Average order value', Money::format($average))
                ->description('Per order, cancelled not counted')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('primary'),
            Stat::make('Customers', $customers)
                ->description($returning . ' came back for more')
                ->descriptionIcon(Heroicon::OutlinedUsers)
                ->color('info'),
            Stat::make('Products', Product::count())
                ->description(Category::count() . ' categories · ' . $onSale . ' on discount')
                ->descriptionIcon(Heroicon::OutlinedShoppingBag)
                ->color('gray')
                ->url(ProductResource::getUrl('index')),
        ];
    }
}