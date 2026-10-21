<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Collection;

class Cart
{
    // TEMP: শুধু পুরনো view না ভাঙার জন্য। Step E2-তে মুছে যাবে। আসল সীমা = stock।
    public const MAX_QTY = 10;

    // ভুল/অস্বাভাবিক সংখ্যা ঠেকানোর জন্য (stock-ই আসল সীমা)
    private const SANITY_MAX = 1000;

    private const KEY = 'cart_v2';
    private const OLD_KEY = 'cart';

    // "12-3" = product 12, variant 3. Variant ছাড়া product = "12-0"
    public static function key(int $productId, int $variantId = 0): string
    {
        return $productId . '-' . $variantId;
    }

    // "12-3" -> [12, 3]; "12" -> [12, 0]; ভুল হলে null
    public static function parseKey(string $key): ?array
    {
        if (! preg_match('/^(\d+)(?:-(\d+))?$/', $key, $m)) {
            return null;
        }

        return [(int) $m[1], (int) ($m[2] ?? 0)];
    }

    // [key => quantity], visitor-এর session-এ থাকে
    public static function raw(): array
    {
        $stored = session(self::KEY);
        $migrated = false;

        // পুরনো ফরম্যাট ([product_id => qty]) নতুন ফরম্যাটে রূপান্তর
        if ($stored === null) {
            $stored = [];
            $old = session(self::OLD_KEY);

            if (is_array($old)) {
                foreach ($old as $id => $qty) {
                    $stored[self::key((int) $id, 0)] = $qty;
                }
            }

            $migrated = true;
        }

        $clean = [];

        if (is_array($stored)) {
            foreach ($stored as $key => $qty) {
                $parsed = self::parseKey((string) $key);
                $qty = (int) $qty;

                if ($parsed && $parsed[0] > 0 && $qty > 0) {
                    $clean[self::key($parsed[0], $parsed[1])] = min($qty, self::SANITY_MAX);
                }
            }
        }

        if ($migrated) {
            session([self::KEY => $clean]);
            session()->forget(self::OLD_KEY);
        }

        return $clean;
    }

    // মোট কয়টা piece (header badge)
    public static function count(): int
    {
        return array_sum(self::raw());
    }

    // cart-এ এই product/variant এখন কয়টা আছে
    public static function quantityOf(int $productId, int $variantId = 0): int
    {
        return self::raw()[self::key($productId, $variantId)] ?? 0;
    }

    // piece যোগ করে (stock-এর সীমা controller আগেই দেখে নেয়)
    public static function add(int $productId, int $variantId, int $quantity): void
    {
        $cart = self::raw();
        $key = self::key($productId, $variantId);

        $cart[$key] = min(($cart[$key] ?? 0) + $quantity, self::SANITY_MAX);
        session([self::KEY => $cart]);
    }

    // ঠিক এই সংখ্যা বসায় (০ বা কম হলে সরিয়ে দেয়)
    public static function set(string $key, int $quantity): void
    {
        $cart = self::raw();

        if (! isset($cart[$key])) {
            return;
        }

        if ($quantity <= 0) {
            unset($cart[$key]);
        } else {
            $cart[$key] = min($quantity, self::SANITY_MAX);
        }

        session([self::KEY => $cart]);
    }

    public static function remove(string $key): void
    {
        $cart = self::raw();
        unset($cart[$key]);
        session([self::KEY => $cart]);
    }

    public static function clear(): void
    {
        session()->forget(self::KEY);
        session()->forget(self::OLD_KEY);
    }

    // "Gold Ring (Size: 7, Color: Gold)"
    public static function lineName(object $line): string
    {
        return $line->label
            ? $line->product->name . ' (' . $line->label . ')'
            : $line->product->name;
    }

    /**
     * Cart-এর লাইনগুলো, database-এর LIVE দাম ও stock সহ।
     * মুছে ফেলা product/variant এখানে চুপচাপ বাদ যায়।
     * Quantity এখানে বদলায় না (stock মেলানোর কাজ reconcile()-এর)।
     */
    public static function lines(): Collection
    {
        $raw = self::raw();

        if (! $raw) {
            return collect();
        }

        $parsed = [];
        foreach ($raw as $key => $quantity) {
            $parsed[$key] = self::parseKey($key);
        }

        $productIds = array_unique(array_column($parsed, 0));
        $products = Product::with(['images', 'variants'])->whereIn('id', $productIds)->get()->keyBy('id');

        $lines = collect();
        $kept = [];

        foreach ($raw as $key => $quantity) {
            [$productId, $variantId] = $parsed[$key];

            $product = $products->get($productId);

            if (! $product) {
                continue;
            }

            $variant = null;

            if ($product->has_variants) {
                $variant = $product->variants->firstWhere('id', $variantId);

                if (! $variant) {
                    continue; // variant মুছে গেছে বা কখনো বাছা হয়নি
                }
            } elseif ($variantId !== 0) {
                continue;
            }

            [$regular, $unit] = $product->linePrices($variant);

            $kept[$key] = $quantity;

            // ছবি: variant-এর choice-এর সাথে বাঁধা ছবি আগে, না থাকলে "সবার" ছবি, না থাকলে প্রথম ছবি
            $image = null;

            if ($variant) {
                $image = $product->images->first(
                    fn ($img) => filled($img->option_name)
                        && (string) ($variant->combination[$img->option_name] ?? '') === (string) $img->option_value
                );
            }

            $image ??= $product->images->first(fn ($img) => blank($img->option_name)) ?? $product->images->first();

            $lines->push((object) [
                'key' => $key,
                'product' => $product,
                'variant' => $variant,
                'label' => $variant?->label(),
                'sku' => $variant?->sku ?? $product->sku,
                'image' => $image,
                'quantity' => $quantity,
                'stock' => $product->stockFor($variant),
                'regular_price' => $regular,
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

    /**
     * Stock-এর সাথে cart মেলায়: বেশি হলে কমিয়ে stock-এর সমান, stock ০ হলে সরিয়ে দেয়।
     * ফেরত দেয়: গ্রাহকের জন্য বার্তার তালিকা (কিছু না বদলালে খালি)।
     */
    public static function reconcile(): array
    {
        $lines = self::lines();
        $cart = self::raw();
        $messages = [];

        foreach ($lines as $line) {
            $name = self::lineName($line);

            if ($line->stock <= 0) {
                unset($cart[$line->key]);
                $messages[] = __(':name is sold out and was removed from your cart.', ['name' => $name]);
            } elseif ($line->quantity > $line->stock) {
                $cart[$line->key] = $line->stock;
                $messages[] = __(':name: only :stock left, your cart was changed to :stock.', [
                    'name' => $name,
                    'stock' => $line->stock,
                ]);
            }
        }

        if ($messages) {
            session([self::KEY => $cart]);
        }

        return $messages;
    }
}