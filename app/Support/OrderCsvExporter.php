<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Orders: one row per order item. Order totals are written only on the FIRST row of each order,
 * so adding up the order_total column never counts an order twice.
 */
class OrderCsvExporter
{
    public const HEADER = [
        'order_code', 'placed_at', 'status', 'customer_name', 'phone', 'email', 'address', 'delivery_zone',
        'product', 'variant', 'sku', 'quantity', 'unit_price', 'line_total',
        'subtotal', 'delivery_charge', 'order_total',
    ];

    public static function download(?string $from, ?string $to, array $statuses): StreamedResponse
    {
        $query = Order::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->when($statuses, fn ($q) => $q->whereIn('status', $statuses))
            ->with('items');

        return CsvWriter::download(
            'orders-' . now()->format('Y-m-d_His') . '.csv',
            self::HEADER,
            function (callable $write) use ($query): void {
                $query->chunkById(200, function ($orders) use ($write): void {
                    foreach ($orders as $order) {
                        self::writeOrder($order, $write);
                    }
                });
            }
        );
    }

    private static function writeOrder(Order $order, callable $write): void
    {
        $head = [
            CsvWriter::text($order->code),
            $order->created_at?->format('Y-m-d H:i'),
            Order::statusOptions()[$order->status] ?? $order->status,
            CsvWriter::text($order->customer_name),
            CsvWriter::text($order->phone),
            CsvWriter::text($order->email),
            CsvWriter::text($order->address),
            CsvWriter::text($order->delivery_zone_name),
        ];

        $totals = [
            CsvWriter::number($order->subtotal),
            CsvWriter::number($order->delivery_charge),
            CsvWriter::number($order->total),
        ];

        if ($order->items->isEmpty()) {
            $write(array_merge($head, ['', '', '', '', '', ''], $totals));

            return;
        }

        foreach ($order->items->values() as $i => $item) {
            $write(array_merge($head, [
                CsvWriter::text($item->product_name),
                CsvWriter::text($item->variant_label),
                CsvWriter::text($item->sku),
                (int) $item->quantity,
                CsvWriter::number($item->unit_price),
                CsvWriter::number($item->line_total),
            ], $i === 0 ? $totals : ['', '', '']));
        }
    }
}