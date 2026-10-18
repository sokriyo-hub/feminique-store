<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Collection;

class Cart
{
    public const MAX_QTY = 10;

    private const KEY = 'cart';

    // [product_id => quantity], saved in the visitor's session
    public static function raw(): array
    {
        $raw = session(self::KEY, []);
        $clean = [];

        if (is_array($raw)) {
            foreach ($raw as $id => $qty) {
                $id = (int) $id;
                $qty = (int) $qty;

                if ($id > 0 && $qty > 0) {
                    $clean[$id] = min($qty, self::MAX_QTY);
                }
            }
        }

        return $clean;
    }

    // Total number of pieces (used for the header badge)
    public static function count(): int
    {
        return array_sum(self::raw());
    }

    // Adds pieces. Returns true when the per-item limit capped the quantity.
    public static function add(int $productId, int $quantity): bool
    {
        $cart = self::raw();
        $new = ($cart[$productId] ?? 0) + $quantity;
        $capped = $new > self::MAX_QTY;

        $cart[$productId] = min($new, self::MAX_QTY);
        session([self::KEY => $cart]);

        return $capped;
    }

    // Sets an exact quantity (0 or less removes the item)
    public static function set(int $productId, int $quantity): void
    {
        $cart = self::raw();

        if (! isset($cart[$productId])) {
            return;
        }

        if ($quantity <= 0) {
            unset($cart[$productId]);
        } else {
            $cart[$productId] = min($quantity, self::MAX_QTY);
        }

        session([self::KEY => $cart]);
    }

    public static function remove(int $productId): void
    {
        $cart = self::raw();
        unset($cart[$productId]);
        session([self::KEY => $cart]);
    }

    public static function clear(): void
    {
        session()->forget(self::KEY);
    }

    // Cart lines with LIVE prices from the database.
    // Products deleted in admin are dropped from the cart automatically.
    public static function lines(): Collection
    {
        $raw = self::raw();

        if (! $raw) {
            return collect();
        }

        $products = Product::with('images')->whereIn('id', array_keys($raw))->get()->keyBy('id');

        $lines = collect();
        $kept = [];

        foreach ($raw as $id => $quantity) {
            $product = $products->get($id);

            if (! $product) {
                continue;
            }

            $unit = $product->selling_price;
            $kept[$id] = $quantity;

            $lines->push((object) [
                'product' => $product,
                'quantity' => $quantity,
                'unit_price' => $unit,
                'line_total' => round($unit * $quantity, 2),
            ]);
        }

        if (count($kept) !== count($raw)) {
            session([self::KEY => $kept]);
        }

        return $lines;
    }

    public static function subtotal(Collection $lines): float
    {
        return round((float) $lines->sum('line_total'), 2);
    }
}