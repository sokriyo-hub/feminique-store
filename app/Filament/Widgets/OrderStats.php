<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OrderStats extends StatsOverviewWidget
{
    protected static ?int $sort = -10;

    protected function getStats(): array
    {
        $rows = Order::query()
            ->selectRaw('status, COUNT(*) as orders, SUM(total) as amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $count = fn (array $statuses): int => (int) collect($statuses)
            ->sum(fn (string $s) => (int) ($rows->get($s)?->orders ?? 0));

        $amount = fn (array $statuses): float => (float) collect($statuses)
            ->sum(fn (string $s) => (float) ($rows->get($s)?->amount ?? 0));

        // Small line under each card: last 7 days (by the day the order was placed)
        $since = now()->subDays(6)->startOfDay();
        $recent = Order::where('created_at', '>=', $since)->get(['created_at', 'status', 'total']);

        $series = function (array $statuses, bool $money) use ($recent, $since): array {
            $out = [];

            for ($i = 0; $i < 7; $i++) {
                $day = $since->copy()->addDays($i)->format('Y-m-d');

                $dayOrders = $recent->filter(
                    fn ($order) => in_array($order->status, $statuses, true)
                        && $order->created_at->format('Y-m-d') === $day
                );

                $out[] = $money ? (float) $dayOrders->sum('total') : $dayOrders->count();
            }

            return $out;
        };

        $all = $count(Order::STATUSES);
        $cancelled = $count(['cancelled']);
        $cancelRate = $all > 0 ? round($cancelled / $all * 100) : 0;

        $url = OrderResource::getUrl('index');

        return [
            Stat::make('Pending orders', $count(['pending']))
                ->description($count(['pending']) > 0
                    ? Money::format($amount(['pending'])) . ' waiting for you'
                    : 'No new orders')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color($count(['pending']) > 0 ? 'warning' : 'success')
                ->chart($series(['pending'], false))
                ->url($url),
            Stat::make('In progress', $count(['confirmed', 'shipped']))
                ->description(Money::format($amount(['confirmed', 'shipped'])) . ' confirmed + shipped')
                ->descriptionIcon(Heroicon::OutlinedTruck)
                ->color('info')
                ->chart($series(['confirmed', 'shipped'], false))
                ->url($url),
            Stat::make('Delivered amount', Money::format($amount(['delivered'])))
                ->description($count(['delivered']) . ' orders delivered')
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->chart($series(['delivered'], true))
                ->url($url),
            Stat::make('Cancelled', $cancelled)
                ->description($cancelRate . '% of all orders')
                ->descriptionIcon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->chart($series(['cancelled'], false))
                ->url($url),
        ];
    }
}