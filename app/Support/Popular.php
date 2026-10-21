<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Customer Favorites": best sellers of the last 90 days (cancelled orders not counted).
 * If there are not enough, it is filled up with the newest products.
 */
class Popular
{
    public const DAYS = 90;

    /**
     * @param  array|null  $categoryIds  null = every category, otherwise only these category ids
     * @param  array  $fillExclude  product ids that must NOT be used when filling up with newest products
     */
    public static function products(?array $categoryIds = null, int $limit = 8, array $fillExclude = []): Collection
    {
        $soldIds = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.status', '!=', 'cancelled')
            ->where('orders.created_at', '>=', now()->subDays(self::DAYS))
            ->when($categoryIds !== null, fn ($query) => $query->whereIn('products.category_id', $categoryIds))
            ->groupBy('order_items.product_id')
            ->orderByRaw('SUM(order_items.quantity) DESC')
            ->orderByDesc('order_items.product_id')
            ->limit($limit)
            ->pluck('order_items.product_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $products = Product::with(['images', 'variants'])
            ->whereIn('id', $soldIds)
            ->get()
            ->sortBy(fn (Product $product) => array_search($product->id, $soldIds, true))
            ->values();

        $missing = $limit - $products->count();

        if ($missing > 0) {
            $fill = Product::with(['images', 'variants'])
                ->when($categoryIds !== null, fn ($query) => $query->whereIn('category_id', $categoryIds))
                ->whereNotIn('id', array_merge($soldIds, $fillExclude))
                ->latest()
                ->orderByDesc('id')
                ->limit($missing)
                ->get();

            $products = $products->concat($fill)->values();
        }

        return $products;
    }
}