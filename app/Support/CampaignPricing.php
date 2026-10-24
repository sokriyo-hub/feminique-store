<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\Category;
use Illuminate\Support\Collection;

/**
 * Which campaigns are running right now, and what they do to prices and delivery.
 * Everything is looked up once per request.
 */
class CampaignPricing
{
    private static ?Collection $active = null;

    private static ?array $children = null;

    private static array $covered = [];

    private static array $productSets = [];

    // Forget what was loaded (used by tests)
    public static function flush(): void
    {
        self::$active = null;
        self::$children = null;
        self::$covered = [];
        self::$productSets = [];
    }

    // Campaigns that are on right now (with their categories and chosen products)
    public static function active(): Collection
    {
        return self::$active ??= Campaign::running()->with(['categories', 'products:id'])->get();
    }

    /**
     * The best discount % that applies to a product (0 = none).
     * A campaign covers the product when it is for all products, or the product's category is chosen
     * (a chosen category includes everything below it), or the product itself is chosen.
     */
    public static function percentFor(int $categoryId, int $productId = 0): int
    {
        $best = 0;

        foreach (self::active() as $campaign) {
            if ($campaign->discount_percent <= $best) {
                continue;
            }

            if (
                $campaign->all_products
                || isset(self::covered($campaign)[$categoryId])
                || ($productId > 0 && isset(self::productSet($campaign)[$productId]))
            ) {
                $best = $campaign->discount_percent;
            }
        }

        return $best;
    }

    // A running campaign with "free delivery" makes delivery free for every order
    public static function freeDelivery(): bool
    {
        return self::freeDeliveryCampaign() !== null;
    }

    public static function freeDeliveryCampaign(): ?Campaign
    {
        return self::active()->first(fn (Campaign $campaign): bool => $campaign->free_delivery);
    }

    // The campaign banner for one place (highest discount, then ends first). null = none.
    public static function bannerFor(string $slot): ?Campaign
    {
        return self::active()
            ->filter(fn (Campaign $campaign): bool => $campaign->show_banner && in_array($slot, $campaign->bannerSlots(), true))
            ->sort(fn (Campaign $a, Campaign $b): int => ($b->discount_percent <=> $a->discount_percent)
                ?: ($a->ends_at->getTimestamp() <=> $b->ends_at->getTimestamp()))
            ->first();
    }

    // [productId => index] of the products chosen one by one
    private static function productSet(Campaign $campaign): array
    {
        return self::$productSets[$campaign->id] ??= array_flip(
            $campaign->products->pluck('id')->map(fn ($id) => (int) $id)->all()
        );
    }

    // [categoryId => true] for the chosen categories AND everything below them
    private static function covered(Campaign $campaign): array
    {
        if (! isset(self::$covered[$campaign->id])) {
            $children = self::childrenMap();
            $queue = $campaign->categories->pluck('id')->map(fn ($id) => (int) $id)->all();
            $set = [];
            $guard = 0;

            while ($queue && $guard++ < 5000) {
                $id = array_shift($queue);

                if (isset($set[$id])) {
                    continue;
                }

                $set[$id] = true;

                foreach ($children[$id] ?? [] as $child) {
                    $queue[] = $child;
                }
            }

            self::$covered[$campaign->id] = $set;
        }

        return self::$covered[$campaign->id];
    }

    // [parentId => [childId, ...]]
    private static function childrenMap(): array
    {
        if (self::$children === null) {
            self::$children = [];

            foreach (Category::query()->get(['id', 'parent_id']) as $row) {
                if ($row->parent_id !== null) {
                    self::$children[(int) $row->parent_id][] = (int) $row->id;
                }
            }
        }

        return self::$children;
    }
}