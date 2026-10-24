<?php

namespace App\Models;

use App\Support\CampaignPricing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    public const MAX_OPTIONS = 3;

    protected $fillable = [
        'category_id', 'name', 'slug', 'sku', 'description', 'price', 'discount_price',
        'has_variants', 'stock',
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
            'has_variants' => 'boolean',
            'stock' => 'integer',
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

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
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

    // মোট stock: variant থাকলে সব variant-এর যোগফল, না থাকলে product-এর নিজের stock
    public function getTotalStockAttribute(): int
    {
        if ($this->has_variants) {
            // তালিকায় withSum('variants', 'stock') দেওয়া থাকলে সেটাই নেয় (বাড়তি query লাগে না)
            if (array_key_exists('variants_sum_stock', $this->attributes)) {
                return (int) $this->attributes['variants_sum_stock'];
            }

            // variants আগে থেকে load করা থাকলে সেখান থেকেই যোগ করে
            if ($this->relationLoaded('variants')) {
                return (int) $this->variants->sum('stock');
            }

            return (int) $this->variants()->sum('stock');
        }

        return (int) $this->stock;
    }

    public function getInStockAttribute(): bool
    {
        return $this->total_stock > 0;
    }

    // ডিসকাউন্ট আছে কিনা (discount_price থাকলে এবং price-এর চেয়ে কম হলে)
    // NOTE: this is the product's OWN discount only (admin and CSV use it). Campaigns are not included.
    public function getHasDiscountAttribute(): bool
    {
        return $this->discount_price !== null
            && (float) $this->discount_price < (float) $this->price;
    }

    // বিক্রির দাম: ডিসকাউন্ট থাকলে discount_price, না থাকলে price (campaign ছাড়া)
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

    /**
     * Campaign rule: the customer gets the LOWER of
     *  - the price the product already sells at (its own discount, if any), and
     *  - the campaign price (regular price minus the campaign %, whole taka).
     * Two discounts are never added together.
     */
    private function applyCampaign(float $regular, float $selling): float
    {
        $percent = CampaignPricing::percentFor((int) $this->category_id, (int) $this->id);

        if ($percent <= 0) {
            return $selling;
        }

        $campaign = (float) round($regular * (100 - $percent) / 100);

        return $campaign < $selling ? $campaign : $selling;
    }

    // The price a customer pays right now for a product without variants (campaign included)
    public function sellingNow(): float
    {
        return $this->applyCampaign((float) $this->price, $this->selling_price);
    }

    /**
     * One variant's prices: [regular price, selling price].
     * - Variant has its own price: that price, and only its own discount.
     * - Variant has no price: the product's price (and the product's discount, unless the variant has its own).
     * - A running campaign can lower the selling price further. Pass $withCampaign = false for the base price.
     */
    public function variantPrices(ProductVariant $variant, bool $withCampaign = true): array
    {
        $own = $variant->price !== null ? (float) $variant->price : null;
        $regular = $own ?? (float) $this->price;

        if ($variant->discount_price !== null) {
            $discount = (float) $variant->discount_price;
        } elseif ($own === null && $this->has_discount) {
            $discount = (float) $this->discount_price;
        } else {
            $discount = null;
        }

        $selling = ($discount !== null && $discount < $regular) ? $discount : $regular;

        if ($withCampaign) {
            $selling = $this->applyCampaign($regular, $selling);
        }

        return [$regular, $selling];
    }

    /**
     * Cart/order line-এর stock: variant থাকলে variant-এর stock, না থাকলে product-এর নিজের stock.
     */
    public function stockFor(?ProductVariant $variant = null): int
    {
        if ($this->has_variants) {
            return $variant ? (int) $variant->stock : 0;
        }

        return (int) $this->stock;
    }

    /**
     * Cart/order line-এর দাম: [regular, selling]. Variant product হলে variant-এর হিসাবে।
     * Campaign included unless $withCampaign = false.
     */
    public function linePrices(?ProductVariant $variant = null, bool $withCampaign = true): array
    {
        if ($this->has_variants && $variant) {
            return $this->variantPrices($variant, $withCampaign);
        }

        $selling = $this->selling_price;

        if ($withCampaign) {
            $selling = $this->applyCampaign((float) $this->price, $selling);
        }

        return [(float) $this->price, $selling];
    }

    /**
     * Price shown on product cards (campaign included).
     * With variants: the lowest selling price, and "from" = true when the variant prices differ.
     */
    public function cardPricing(): array
    {
        $regular = (float) $this->price;
        $selling = $this->sellingNow();
        $from = false;

        if ($this->has_variants && $this->variants->isNotEmpty()) {
            $best = null;
            $sellingPrices = [];

            foreach ($this->variants as $variant) {
                [$r, $s] = $this->variantPrices($variant);
                $sellingPrices[] = round($s, 2);

                if ($best === null || $s < $best[1]) {
                    $best = [$r, $s];
                }
            }

            [$regular, $selling] = $best;
            $from = count(array_unique($sellingPrices)) > 1;
        }

        $hasDiscount = $selling < $regular;

        return [
            'regular' => $regular,
            'selling' => $selling,
            'has_discount' => $hasDiscount,
            'percent' => ($hasDiscount && $regular > 0) ? (int) round(($regular - $selling) / $regular * 100) : 0,
            'from' => $from,
        ];
    }
}