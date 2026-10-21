<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Order status change + stock:
 * - to Cancelled: stock goes back (only once, controlled by orders.stock_deducted)
 * - Cancelled to active again: stock is taken again (blocked if there is not enough)
 */
class OrderStock
{
    /**
     * Returns null on success, or an error message (then NOTHING was changed).
     */
    public static function changeStatus(Order $order, string $newStatus): ?string
    {
        if (! in_array($newStatus, Order::STATUSES, true)) {
            return 'Unknown status.';
        }

        return DB::transaction(function () use ($order, $newStatus): ?string {
            $locked = Order::with('items')->lockForUpdate()->find($order->id);

            if (! $locked) {
                return 'Order not found.';
            }

            if ($locked->status === $newStatus) {
                return null;
            }

            $cancelling = $newStatus === 'cancelled';
            $reactivating = $locked->status === 'cancelled' && ! $cancelling;

            if ($cancelling && $locked->stock_deducted) {
                self::restore($locked);
                $locked->stock_deducted = false;
            } elseif ($reactivating && ! $locked->stock_deducted) {
                $error = self::deduct($locked);

                if ($error !== null) {
                    return $error;
                }

                $locked->stock_deducted = true;
            }

            $locked->status = $newStatus;
            $locked->save();

            return null;
        });
    }

    /**
     * Quantities to move, grouped: [products => [id => qty], variants => [id => qty], names => [key => text]]
     * Lines whose product/variant was deleted later are skipped (nothing to update).
     */
    private static function targets(Order $order): array
    {
        $products = [];
        $variants = [];
        $names = [];

        foreach ($order->items as $item) {
            $qty = (int) $item->quantity;

            if ($item->product_variant_id) {
                $id = (int) $item->product_variant_id;
                $variants[$id] = ($variants[$id] ?? 0) + $qty;
                $names['v' . $id] = $item->product_name . ' (' . $item->variant_label . ')';
            } elseif ($item->product_id && blank($item->variant_label)) {
                $id = (int) $item->product_id;
                $products[$id] = ($products[$id] ?? 0) + $qty;
                $names['p' . $id] = $item->product_name;
            }
        }

        return [$products, $variants, $names];
    }

    private static function restore(Order $order): void
    {
        [$products, $variants] = self::targets($order);

        foreach ($variants as $id => $qty) {
            ProductVariant::whereKey($id)->increment('stock', $qty);
        }

        foreach ($products as $id => $qty) {
            Product::whereKey($id)->increment('stock', $qty);
        }
    }

    // Returns an error message when stock is not enough (nothing is changed then)
    private static function deduct(Order $order): ?string
    {
        [$products, $variants, $names] = self::targets($order);

        // Lock rows always in the same order (by id), like checkout does
        $productRows = $products
            ? Product::whereIn('id', array_keys($products))->orderBy('id')->lockForUpdate()->get()->keyBy('id')
            : collect();

        $variantRows = $variants
            ? ProductVariant::whereIn('id', array_keys($variants))->orderBy('id')->lockForUpdate()->get()->keyBy('id')
            : collect();

        $short = [];

        foreach ($variants as $id => $qty) {
            $row = $variantRows->get($id);

            if ($row && (int) $row->stock < $qty) {
                $short[] = $names['v' . $id] . ': needs ' . $qty . ', only ' . (int) $row->stock . ' left';
            }
        }

        foreach ($products as $id => $qty) {
            $row = $productRows->get($id);

            if ($row && (int) $row->stock < $qty) {
                $short[] = $names['p' . $id] . ': needs ' . $qty . ', only ' . (int) $row->stock . ' left';
            }
        }

        if ($short) {
            return 'Not enough stock to re-open this order. ' . implode('; ', $short) . '.';
        }

        foreach ($variantRows as $id => $row) {
            $row->decrement('stock', $variants[$id]);
        }

        foreach ($productRows as $id => $row) {
            $row->decrement('stock', $products[$id]);
        }

        return null;
    }
}