<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;

/**
 * The ONE place that locks stock rows and takes stock for an order.
 * Used by the website checkout and by the admin Order desk, so the stock rules can never drift apart.
 */
class OrderLines
{
    /**
     * Locks the product and variant rows (always in id order, so two orders never block each other).
     * Call this INSIDE a database transaction.
     * Returns [products keyed by id, variants keyed by id].
     */
    public static function lock(array $productIds, array $variantIds): array
    {
        $products = Product::whereIn('id', array_values(array_unique($productIds)))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $variantIds = array_values(array_filter(array_unique($variantIds)));

        $variants = $variantIds
            ? ProductVariant::whereIn('id', $variantIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id')
            : collect();

        return [$products, $variants];
    }

    /**
     * Saves the order lines (a snapshot of name, price, variant and SKU) and reduces the stock.
     * $rows: each = product, variant (or null), quantity, regular, unit, total.
     * Call this INSIDE the same transaction, after the stock was checked.
     */
    public static function save(Order $order, array $rows): void
    {
        foreach ($rows as $row) {
            $product = $row['product'];
            $variant = $row['variant'];

            $order->items()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'product_name' => $product->name,
                'variant_label' => $variant?->label(),
                'sku' => $variant?->sku ?? $product->sku,
                'regular_price' => $row['regular'],
                'unit_price' => $row['unit'],
                'quantity' => $row['quantity'],
                'line_total' => $row['total'],
            ]);

            if ($variant) {
                $variant->decrement('stock', $row['quantity']);
            } else {
                $product->decrement('stock', $row['quantity']);
            }
        }
    }
}