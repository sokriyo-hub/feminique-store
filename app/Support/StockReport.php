<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Stock numbers for the admin dashboard and the Products menu badge.
 * One product's stock = its own stock, or the sum of its variants when it has variants.
 */
class StockReport
{
    // "Low stock" limit (Settings > Stock alerts). Default 5.
    public static function threshold(): int
    {
        return max(0, (int) Setting::get('low_stock_threshold', '5'));
    }

    // SQL for "total stock of one product" (use inside whereRaw / selectRaw on the products table)
    public static function totalSql(): string
    {
        return '(CASE WHEN products.has_variants = 1 THEN COALESCE((SELECT SUM(product_variants.stock) FROM product_variants WHERE product_variants.product_id = products.id), 0) ELSE products.stock END)';
    }

    public static function totalPieces(): int
    {
        return (int) DB::table('products')->selectRaw('COALESCE(SUM(' . self::totalSql() . '), 0) as t')->value('t');
    }

    // Stock is 0
    public static function outCount(): int
    {
        return DB::table('products')->whereRaw(self::totalSql() . ' <= 0')->count();
    }

    // Stock is more than 0 but not more than the limit
    public static function lowCount(): int
    {
        return DB::table('products')
            ->whereRaw(self::totalSql() . ' > 0 AND ' . self::totalSql() . ' <= ?', [self::threshold()])
            ->count();
    }

    public static function lowOrOutCount(): int
    {
        return DB::table('products')->whereRaw(self::totalSql() . ' <= ?', [self::threshold()])->count();
    }
}