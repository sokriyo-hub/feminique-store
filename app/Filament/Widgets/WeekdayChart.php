<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class WeekdayChart extends ChartWidget
{
    protected static ?int $sort = -6;

    public function getHeading(): ?string
    {
        return 'Busiest days';
    }

    public function getDescription(): ?string
    {
        return 'Orders by weekday, last 90 days.';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $byDay = Order::where('status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->subDays(89)->startOfDay())
            ->get(['created_at'])
            ->countBy(fn ($order) => $order->created_at->dayOfWeekIso);

        $data = [];

        for ($day = 1; $day <= 7; $day++) {
            $data[] = (int) ($byDay[$day] ?? 0);
        }

        return [
            'datasets' => [[
                'label' => 'Orders',
                'data' => $data,
                'backgroundColor' => '#A58263',
                'borderRadius' => 4,
            ]],
            'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['ticks' => ['precision' => 0]]],
        ];
    }
}