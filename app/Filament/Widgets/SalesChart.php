<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class SalesChart extends ChartWidget
{
    protected static ?int $sort = -8;

    protected int|string|array $columnSpan = 'full';

    public ?string $filter = '30';

    public function getHeading(): ?string
    {
        return 'Sales (Tk)';
    }

    public function getDescription(): ?string
    {
        return 'Cancelled orders are not counted.';
    }

    protected function getFilters(): ?array
    {
        return [
            '7' => 'Last 7 days',
            '30' => 'Last 30 days',
            '90' => 'Last 90 days',
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $days = (int) ($this->filter ?? 30);
        $from = now()->subDays($days - 1)->startOfDay();

        $byDay = Order::where('status', '!=', 'cancelled')
            ->where('created_at', '>=', $from)
            ->get(['created_at', 'total'])
            ->groupBy(fn ($order) => $order->created_at->format('Y-m-d'))
            ->map(fn ($group) => (float) $group->sum('total'));

        $labels = [];
        $data = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $from->copy()->addDays($i);
            $labels[] = $date->format('d M');
            $data[] = $byDay[$date->format('Y-m-d')] ?? 0;
        }

        return [
            'datasets' => [[
                'label' => 'Sales (Tk)',
                'data' => $data,
                'borderColor' => '#A58263',
                'backgroundColor' => 'rgba(165, 130, 99, 0.15)',
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
        ];
    }
}