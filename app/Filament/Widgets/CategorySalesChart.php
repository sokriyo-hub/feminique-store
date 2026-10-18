<?php

namespace App\Filament\Widgets;

use App\Models\OrderItem;
use Filament\Widgets\ChartWidget;

class CategorySalesChart extends ChartWidget
{
    protected static ?int $sort = -5;

    public function getHeading(): ?string
    {
        return 'Sales by category (Tk)';
    }

    public function getDescription(): ?string
    {
        return 'Cancelled orders are not counted.';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw('categories.name as label, SUM(order_items.line_total) as total')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return [
            'datasets' => [[
                'label' => 'Sales (Tk)',
                'data' => $rows->map(fn ($row) => (float) $row->total)->all(),
                'backgroundColor' => '#7A5A42',
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
        ];
    }
}