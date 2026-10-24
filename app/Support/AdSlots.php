<?php

namespace App\Support;

use App\Models\Ad;
use App\Models\Campaign;
use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * The places where an ad or a campaign banner can be shown, which of them are switched off,
 * and which of them are already used (one ad OR banner per place).
 */
class AdSlots
{
    // key => [page, place]
    public const SLOTS = [
        'home_after_hero' => ['Home page', 'Under the hero image'],
        'home_after_categories' => ['Home page', 'Under "Shop by Category"'],
        'home_after_new_arrivals' => ['Home page', 'Between "New Arrivals" and "Customer Favorites"'],
        'home_after_favorites' => ['Home page', 'Under "Customer Favorites"'],
        'category_above_subcategories' => ['Category page', 'Above the sub-category row'],
        'category_below_subcategories' => ['Category page', 'Below the sub-category row (only on categories that have sub-categories)'],
        'category_bottom' => ['Category page', 'At the bottom of the page'],
        'product_above' => ['Product page', 'Above the product'],
        'product_below' => ['Product page', 'Below the product'],
        'cart_bottom' => ['Cart page', 'Under the cart'],
        'checkout_above' => ['Checkout page', 'Above the checkout form'],
        'checkout_below' => ['Checkout page', 'Below the checkout form'],
    ];

    public const DEFAULT_CAMPAIGN_SLOT = 'home_after_categories';

    private static ?array $off = null;

    private static array $taken = [];

    public static function flush(): void
    {
        self::$off = null;
        self::$taken = [];
    }

    // Keys of the places that are switched off
    public static function off(): array
    {
        if (self::$off === null) {
            $list = json_decode((string) Setting::get('ad_slots_off'), true);

            self::$off = is_array($list) ? array_values(array_filter($list, 'is_string')) : [];
        }

        return self::$off;
    }

    public static function isOn(string $slot): bool
    {
        return isset(self::SLOTS[$slot]) && ! in_array($slot, self::off(), true);
    }

    // "Home page: Under the hero image"
    public static function label(string $slot): string
    {
        $row = self::SLOTS[$slot] ?? null;

        return $row ? $row[0] . ': ' . $row[1] : $slot;
    }

    // [key => "Home page: Under the hero image"], places that are off get a note
    public static function options(): array
    {
        $options = [];

        foreach (self::SLOTS as $key => $row) {
            $options[$key] = $row[0] . ': ' . $row[1] . (self::isOn($key) ? '' : ' (turned off)');
        }

        return $options;
    }

    // [page => [key => place]] for the "Ad places" admin page
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::SLOTS as $key => $row) {
            $groups[$row[0]][$key] = $row[1];
        }

        return $groups;
    }

    // Does something that is switched on and not finished block a place? (used by the forms)
    public static function occupies(bool $active, mixed $endsAt): bool
    {
        return $active && (blank($endsAt) || ! Carbon::parse($endsAt)->isPast());
    }

    /**
     * Places that are already used by an ad or a campaign banner that is switched on and not finished.
     * Returns [slot => 'Ad "Name"' | 'Campaign banner "Name"'].
     * Pass the record you are editing, so it does not block its own places.
     */
    public static function taken(?string $exceptType = null, ?int $exceptId = null): array
    {
        $key = ($exceptType ?? '') . ':' . ($exceptId ?? 0);

        if (isset(self::$taken[$key])) {
            return self::$taken[$key];
        }

        $taken = [];

        $ads = Ad::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->when($exceptType === 'ad' && $exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->orderBy('id')
            ->get(['id', 'name', 'placements']);

        foreach ($ads as $ad) {
            foreach ((array) $ad->placements as $slot) {
                if (isset(self::SLOTS[$slot])) {
                    $taken[$slot] ??= 'Ad "' . $ad->name . '"';
                }
            }
        }

        $campaigns = Campaign::query()
            ->where('is_active', true)
            ->where('show_banner', true)
            ->where('ends_at', '>=', now())
            ->when($exceptType === 'campaign' && $exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->orderBy('id')
            ->get();

        foreach ($campaigns as $campaign) {
            foreach ($campaign->bannerSlots() as $slot) {
                if (isset(self::SLOTS[$slot])) {
                    $taken[$slot] ??= 'Campaign banner "' . $campaign->name . '"';
                }
            }
        }

        return self::$taken[$key] = $taken;
    }
}