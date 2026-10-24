<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Category;
use App\Models\Product;
use App\Support\CampaignPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CampaignPricing::flush();
    }

    private function category(string $slug, ?int $parentId = null): Category
    {
        return Category::create(['name' => ucfirst($slug), 'slug' => $slug, 'parent_id' => $parentId, 'sort_order' => 1]);
    }

    private function product(Category $category, string $name): Product
    {
        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'price' => 1000,
            'has_variants' => false,
            'stock' => 5,
        ]);
    }

    private function campaign(): Campaign
    {
        return Campaign::create([
            'name' => 'Scoped sale',
            'discount_percent' => 11,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
            'all_products' => false,
        ]);
    }

    public function test_a_campaign_can_cover_only_chosen_products(): void
    {
        $rings = $this->category('rings');
        $chosen = $this->product($rings, 'Chosen Ring');
        $other = $this->product($rings, 'Other Ring');

        $this->campaign()->products()->sync([$chosen->id]);
        CampaignPricing::flush();

        $this->assertSame(890.0, $chosen->fresh()->cardPricing()['selling']);
        $this->assertSame(1000.0, $other->fresh()->cardPricing()['selling']);
    }

    public function test_chosen_categories_and_chosen_products_work_together(): void
    {
        $rings = $this->category('rings');
        $gold = $this->category('gold-rings', $rings->id);
        $necklaces = $this->category('necklaces');

        $inGold = $this->product($gold, 'Gold Ring');
        $chosenNecklace = $this->product($necklaces, 'Chosen Necklace');
        $otherNecklace = $this->product($necklaces, 'Other Necklace');

        $campaign = $this->campaign();
        $campaign->categories()->sync([$rings->id]);
        $campaign->products()->sync([$chosenNecklace->id]);
        CampaignPricing::flush();

        $this->assertSame(890.0, $inGold->fresh()->cardPricing()['selling']);
        $this->assertSame(890.0, $chosenNecklace->fresh()->cardPricing()['selling']);
        $this->assertSame(1000.0, $otherNecklace->fresh()->cardPricing()['selling']);
    }

    public function test_the_best_discount_wins_when_two_campaigns_cover_a_product(): void
    {
        $rings = $this->category('rings');
        $product = $this->product($rings, 'Ring');

        $small = $this->campaign();
        $small->products()->sync([$product->id]);

        $big = $this->campaign();
        $big->update(['discount_percent' => 30, 'all_products' => true]);

        CampaignPricing::flush();

        $this->assertSame(700.0, $product->fresh()->cardPricing()['selling']);
    }
}