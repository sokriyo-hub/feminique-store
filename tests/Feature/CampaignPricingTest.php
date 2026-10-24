<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\CampaignPricing;
use App\Support\Delivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CampaignPricing::flush();
    }

    private function category(string $slug = 'rings', ?int $parentId = null): Category
    {
        return Category::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'parent_id' => $parentId,
            'sort_order' => 1,
        ]);
    }

    private function product(array $attributes = [], ?Category $category = null): Product
    {
        $category ??= Category::first() ?? $this->category();

        return Product::create($attributes + [
            'category_id' => $category->id,
            'name' => 'Test Ring ' . uniqid(),
            'slug' => 'test-ring-' . uniqid(),
            'price' => 1000,
            'discount_price' => null,
            'has_variants' => false,
            'stock' => 5,
        ]);
    }

    private function campaign(array $attributes = []): Campaign
    {
        $campaign = Campaign::create($attributes + [
            'name' => 'Test sale',
            'discount_percent' => 11,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
            'all_products' => true,
        ]);

        CampaignPricing::flush();

        return $campaign;
    }

    public function test_running_campaign_lowers_the_price(): void
    {
        $product = $this->product();

        $this->assertSame(1000.0, $product->cardPricing()['selling']);

        $this->campaign();

        $pricing = $product->cardPricing();

        $this->assertSame(1000.0, $pricing['regular']);
        $this->assertSame(890.0, $pricing['selling']);
        $this->assertTrue($pricing['has_discount']);
        $this->assertSame(11, $pricing['percent']);
    }

    public function test_campaign_that_is_not_running_changes_nothing(): void
    {
        $product = $this->product();

        foreach ([
            ['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)],
            ['starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()],
            ['is_active' => false],
        ] as $attributes) {
            Campaign::query()->delete();
            $this->campaign($attributes);

            $this->assertSame(1000.0, $product->cardPricing()['selling']);
        }
    }

    public function test_the_lower_of_own_discount_and_campaign_wins(): void
    {
        $product = $this->product(['price' => 1000, 'discount_price' => 800]);

        $this->campaign(['discount_percent' => 11]);
        $this->assertSame(800.0, $product->cardPricing()['selling']);

        Campaign::query()->delete();
        $this->campaign(['discount_percent' => 30]);
        $this->assertSame(700.0, $product->cardPricing()['selling']);
    }

    public function test_campaign_can_be_limited_to_categories_and_their_sub_categories(): void
    {
        $rings = $this->category('rings');
        $gold = $this->category('gold-rings', $rings->id);
        $necklaces = $this->category('necklaces');

        $campaign = $this->campaign(['all_products' => false]);
        $campaign->categories()->sync([$rings->id]);
        CampaignPricing::flush();

        $this->assertSame(890.0, $this->product([], $rings)->cardPricing()['selling']);
        $this->assertSame(890.0, $this->product([], $gold)->cardPricing()['selling']);
        $this->assertSame(1000.0, $this->product([], $necklaces)->cardPricing()['selling']);
    }

    public function test_variant_prices_use_the_campaign_but_can_be_read_without_it(): void
    {
        $product = $this->product(['has_variants' => true, 'stock' => 0]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'combination' => ['Size' => '7'],
            'combination_key' => 'Size=7',
            'stock' => 3,
            'sort_order' => 1,
        ]);

        $this->campaign();

        $this->assertSame([1000.0, 890.0], $product->variantPrices($variant));
        // false = the base price (this is what the CSV export writes)
        $this->assertSame([1000.0, 1000.0], $product->variantPrices($variant, false));
    }

    public function test_free_delivery_campaign_makes_delivery_free(): void
    {
        $zone = DeliveryZone::create(['name' => 'Inside Dhaka', 'charge' => 80, 'is_active' => true]);

        $this->assertSame(80.0, Delivery::charge($zone, 500.0));

        $this->campaign(['free_delivery' => true]);

        $this->assertSame(0.0, Delivery::charge($zone, 500.0));
    }

    public function test_order_is_saved_with_the_campaign_price_and_free_delivery(): void
    {
        $product = $this->product(['stock' => 5]);
        DeliveryZone::create(['name' => 'Inside Dhaka', 'charge' => 80, 'is_active' => true]);
        $this->campaign(['free_delivery' => true]);

        $response = $this->withSession(['cart_v2' => [$product->id . '-0' => 2]])->post('/checkout', [
            'name' => 'Test Customer',
            'phone' => '01712345678',
            'address' => 'House 1, Road 1, Dhaka',
            'seen_total' => 1780,
        ]);

        $response->assertRedirect(route('order.placed'));

        $order = Order::with('items')->firstOrFail();

        $this->assertSame('1780.00', (string) $order->total);
        $this->assertSame('0.00', (string) $order->delivery_charge);
        $this->assertSame('890.00', (string) $order->items->first()->unit_price);
        $this->assertSame('1000.00', (string) $order->items->first()->regular_price);
        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_order_is_refused_when_the_price_changed_after_the_customer_saw_it(): void
    {
        $product = $this->product(['stock' => 5]);
        DeliveryZone::create(['name' => 'Inside Dhaka', 'charge' => 80, 'is_active' => true]);

        // The customer saw 2 x 1000 + 80 = 2080, then a campaign started
        $this->campaign();

        $response = $this->withSession(['cart_v2' => [$product->id . '-0' => 2]])->post('/checkout', [
            'name' => 'Test Customer',
            'phone' => '01712345678',
            'address' => 'House 1, Road 1, Dhaka',
            'seen_total' => 2080,
        ]);

        $response->assertRedirect(route('cart'));

        $this->assertSame(0, Order::count());
        $this->assertSame(5, $product->fresh()->stock);
    }
}