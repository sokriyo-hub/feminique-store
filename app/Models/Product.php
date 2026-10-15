<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    protected $fillable = [
        'category_id', 'name', 'slug', 'description', 'price', 'discount_price',
    ];

    protected static function booted(): void
    {
        // product মুছলে তার ছবির ফাইলও ডিস্ক থেকে মুছে যাবে
        static::deleting(function (Product $product) {
            foreach ($product->images as $image) {
                Storage::disk('public')->delete($image->path);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'discount_price' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    // ছবির ফাইল-পাথের তালিকা (ক্রম অনুযায়ী) দিলে product_images টেবিল মিলিয়ে দেয়
    public function syncImages(array $paths): void
    {
        $paths = array_values(array_unique(array_filter($paths)));

        $removed = $this->images()->whereNotIn('path', $paths)->pluck('path');
        $this->images()->whereNotIn('path', $paths)->delete();

        foreach ($removed as $path) {
            Storage::disk('public')->delete($path);
        }

        foreach ($paths as $i => $path) {
            $this->images()->updateOrCreate(['path' => $path], ['sort_order' => $i + 1]);
        }
    }

    // ডিসকাউন্ট আছে কিনা (discount_price থাকলে এবং price-এর চেয়ে কম হলে)
    public function getHasDiscountAttribute(): bool
    {
        return $this->discount_price !== null
            && (float) $this->discount_price < (float) $this->price;
    }

    // বিক্রির দাম: ডিসকাউন্ট থাকলে discount_price, না থাকলে price
    public function getSellingPriceAttribute(): float
    {
        return $this->has_discount
            ? (float) $this->discount_price
            : (float) $this->price;
    }

    // ডিসকাউন্ট % (ডাটাবেসে রাখা হয় না, প্রতিবার হিসাব হয়)
    public function getDiscountPercentAttribute(): int
    {
        if (! $this->has_discount || (float) $this->price <= 0) {
            return 0;
        }

        return (int) round(((float) $this->price - (float) $this->discount_price) / (float) $this->price * 100);
    }
}