<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    public const MAX_PER_PRODUCT = 100;

    protected $fillable = [
        'product_id', 'sku', 'combination', 'combination_key',
        'price', 'discount_price', 'stock', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'combination' => 'array',
            'price' => 'decimal:2',
            'discount_price' => 'decimal:2',
            'stock' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // ['Size' => '7', 'Color' => 'Gold'] -> "Size=7|Color=Gold"
    public static function makeKey(array $combination): string
    {
        $parts = [];

        foreach ($combination as $name => $value) {
            $parts[] = $name . '=' . $value;
        }

        return implode('|', $parts);
    }

    // ['Size' => '7', 'Color' => 'Gold'] -> "Size 7, Gold" style text: "Size: 7, Color: Gold"
    public function label(): string
    {
        $parts = [];

        foreach ((array) $this->combination as $name => $value) {
            $parts[] = $name . ': ' . $value;
        }

        return implode(', ', $parts);
    }

    public function inStock(): bool
    {
        return $this->stock > 0;
    }
}