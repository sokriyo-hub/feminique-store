<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Category;
use App\Models\Product;
use App\Support\CampaignPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignOfferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
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

    private function campaign(array $attributes = []): Campaign
    {
        return Campaign::create(array_merge([
            'name' => 'Festive Sale',
            'discount_percent' => 20,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
            'all_products' => false,
        ], $attributes));
    }

    public function test_an_all_products_campaign_lists_every_product(): void
    {
        $rings = $this->category('rings');
        $this->product($rings, 'First Ring');
        $this->product($this->category('necklaces'), 'First Necklace');

        $campaign = $this->campaign(['all_products' => true]);
        CampaignPricing::flush();

        $this->get(route('offer.show', $campaign->slug))
            ->assertOk()
            ->assertSee('First Ring')
            ->assertSee('First Necklace');
    }

    public function test_a_category_campaign_includes_sub_categories_only(): void
    {
        $rings = $this->category('rings');
        $gold = $this->category('gold-rings', $rings->id);
        $this->product($gold, 'Gold Ring');
        $this->product($this->category('necklaces'), 'Plain Necklace');

        $campaign = $this->campaign();
        $campaign->categories()->sync([$rings->id]);
        CampaignPricing::flush();

        $this->get(route('offer.show', $campaign->slug))
            ->assertOk()
            ->assertSee('Gold Ring')
            ->assertDontSee('Plain Necklace');
    }

    public function test_single_products_from_different_categories_show_together(): void
    {
        $rings = $this->category('rings');
        $necklaces = $this->category('necklaces');

        $chosenRing = $this->product($rings, 'Chosen Ring');
        $chosenNecklace = $this->product($necklaces, 'Chosen Necklace');
        $this->product($rings, 'Other Ring');
        $this->product($necklaces, 'Other Necklace');

        $campaign = $this->campaign();
        $campaign->products()->sync([$chosenRing->id, $chosenNecklace->id]);
        CampaignPricing::flush();

        $this->get(route('offer.show', $campaign->slug))
            ->assertOk()
            ->assertSee('Chosen Ring')
            ->assertSee('Chosen Necklace')
            ->assertDontSee('Other Ring')
            ->assertDontSee('Other Necklace');
    }

    public function test_an_ended_campaign_page_shows_no_products(): void
    {
        $rings = $this->category('rings');
        $product = $this->product($rings, 'Old Offer Ring');

        $campaign = $this->campaign(['starts_at' => now()->subDays(3), 'ends_at' => now()->subDay()]);
        $campaign->products()->sync([$product->id]);
        CampaignPricing::flush();

        $this->get(route('offer.show', $campaign->slug))
            ->assertOk()
            ->assertSee('This offer is not running right now.')
            ->assertDontSee('Old Offer Ring');
    }

    public function test_an_unknown_offer_link_is_a_404(): void
    {
        $this->get('/offers/does-not-exist')->assertNotFound();
    }

    public function test_the_menu_link_shows_only_when_switched_on(): void
    {
        $campaign = $this->campaign(['all_products' => true, 'offer_title' => 'Festive Offers', 'offer_in_menu' => false]);
        CampaignPricing::flush();

        $this->get('/')->assertOk()->assertDontSee('Festive Offers');

        $campaign->update(['offer_in_menu' => true]);
        CampaignPricing::flush();

        $this->get('/')
            ->assertOk()
            ->assertSee('Festive Offers')
            ->assertSee(route('offer.show', $campaign->slug), false);
    }

    public function test_the_banner_button_link(): void
    {
        $campaign = $this->campaign();

        // empty = the offer page
        $this->assertSame(route('offer.show', $campaign->slug), $campaign->bannerLink());

        // a safe link typed by admin
        $campaign->banner_link = '/category/rings';
        $this->assertSame('/category/rings', $campaign->bannerLink());

        $campaign->banner_link = 'https://example.com/sale';
        $this->assertSame('https://example.com/sale', $campaign->bannerLink());

        // an unsafe link is ignored
        $campaign->banner_link = 'javascript:alert(1)';
        $this->assertSame(route('offer.show', $campaign->slug), $campaign->bannerLink());
    }

    public function test_two_campaigns_with_the_same_name_get_different_links(): void
    {
        $first = $this->campaign();
        $second = $this->campaign();

        $this->assertSame('festive-sale', $first->slug);
        $this->assertSame('festive-sale-2', $second->slug);
    }
}