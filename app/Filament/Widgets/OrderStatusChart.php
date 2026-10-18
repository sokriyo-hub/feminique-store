<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class OrderStatusChart extends ChartWidget
{
    protected static ?int $sort = -7;

    public function getHeading(): ?string
    {
        return 'Orders by status';
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $counts = Order::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $names = Order::statusOptions();
        $statuses = Order::STATUSES;

        return [
            'datasets' => [[
                'label' => 'Orders',
                'data' => array_map(fn (string $s): int => (int) ($counts[$s] ?? 0), $statuses),
                'backgroundColor' => ['#F59E0B', '#38BDF8', '#A58263', '#22C55E', '#EF4444'],
            ]],
            'labels' => array_map(fn (string $s): string => $names[$s], $statuses),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['display' => false],
                'y' => ['display' => false],
            ],
            'plugins' => ['legend' => ['position' => 'bottom']],
        ];
    }
}