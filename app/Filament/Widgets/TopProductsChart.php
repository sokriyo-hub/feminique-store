<?php

namespace App\Filament\Widgets;

use App\Models\OrderItem;
use Filament\Widgets\ChartWidget;

class TopProductsChart extends ChartWidget
{
    protected static ?int $sort = -4;

    public function getHeading(): ?string
    {
        return 'Top selling products';
    }

    public function getDescription(): ?string
    {
        return 'Pieces sold, cancelled orders not counted.';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw('order_items.product_name as label, SUM(order_items.quantity) as pieces')
            ->groupBy('order_items.product_name')
            ->orderByDesc('pieces')
            ->limit(5)
            ->get();

        return [
            'datasets' => [[
                'label' => 'Pieces sold',
                'data' => $rows->map(fn ($row) => (int) $row->pieces)->all(),
                'backgroundColor' => '#C6A889',
                'borderRadius' => 4,
            ]],
            'labels' => $rows->pluck('label')->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['x' => ['ticks' => ['precision' => 0]]],
        ];
    }
}