<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\AdminOrderCreator;
use App\Support\CampaignPricing;
use App\Support\OrderStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderDeskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CampaignPricing::flush();
    }

    private function zone(): DeliveryZone
    {
        return DeliveryZone::create(['name' => 'Inside Dhaka', 'charge' => 80, 'is_active' => true]);
    }

    private function product(int $stock = 5, bool $hasVariants = false): Product
    {
        $category = Category::firstOrCreate(['slug' => 'rings'], ['name' => 'Rings', 'sort_order' => 1]);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Desk Ring',
            'slug' => 'desk-ring-' . uniqid(),
            'price' => 1000,
            'has_variants' => $hasVariants,
            'stock' => $stock,
        ]);
    }

    private function variant(Product $product, int $stock): ProductVariant
    {
        return ProductVariant::create([
            'product_id' => $product->id,
            'combination' => ['Size' => '7'],
            'combination_key' => 'Size=7',
            'stock' => $stock,
            'sort_order' => 1,
        ]);
    }

    private function payload(DeliveryZone $zone, array $lines, array $extra = []): array
    {
        return array_merge([
            'name' => 'Walk In Customer',
            'phone' => '+880 1712-345678',
            'email' => null,
            'address' => 'House 1, Road 2, Dhaka',
            'source' => 'facebook',
            'status' => 'confirmed',
            'zone_id' => $zone->id,
            'custom_charge' => null,
            'discount' => 0,
            'lines' => $lines,
        ], $extra);
    }

    public function test_an_admin_order_is_saved_with_source_totals_and_stock(): void
    {
        $zone = $this->zone();
        $product = $this->product(5);

        [$order, $error] = AdminOrderCreator::create($this->payload(
            $zone,
            [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 2, 'unit_price' => 900]],
            ['discount' => 100],
        ));

        $this->assertNull($error);
        $this->assertSame('01712345678', $order->phone);
        $this->assertSame('facebook', $order->source);
        $this->assertTrue($order->created_by_admin);
        $this->assertSame('confirmed', $order->status);
        $this->assertTrue($order->stock_deducted);

        $this->assertSame(1800.0, (float) $order->subtotal);
        $this->assertSame(100.0, (float) $order->discount);
        $this->assertSame(80.0, (float) $order->delivery_charge);
        $this->assertSame(1780.0, (float) $order->total);

        $item = $order->items()->first();
        $this->assertSame(1000.0, (float) $item->regular_price);
        $this->assertSame(900.0, (float) $item->unit_price);

        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_a_custom_delivery_charge_of_zero_means_free_delivery(): void
    {
        $zone = $this->zone();
        $product = $this->product(5);

        [$order] = AdminOrderCreator::create($this->payload(
            $zone,
            [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'unit_price' => 1000]],
            ['custom_charge' => 0],
        ));

        $this->assertSame(0.0, (float) $order->delivery_charge);
        $this->assertSame(1000.0, (float) $order->total);
    }

    public function test_not_enough_stock_saves_nothing(): void
    {
        $zone = $this->zone();
        $product = $this->product(2);

        [$order, $error] = AdminOrderCreator::create($this->payload(
            $zone,
            [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 3, 'unit_price' => 1000]],
        ));

        $this->assertNull($order);
        $this->assertStringContainsString('Not enough stock', $error);
        $this->assertSame(0, Order::count());
        $this->assertSame(2, $product->fresh()->stock);
    }

    public function test_the_same_product_on_two_lines_is_added_together_for_stock(): void
    {
        $zone = $this->zone();
        $product = $this->product(5);

        [$order, $error] = AdminOrderCreator::create($this->payload($zone, [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 3, 'unit_price' => 1000],
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 3, 'unit_price' => 1000],
        ]));

        $this->assertNull($order);
        $this->assertStringContainsString('Not enough stock', $error);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_a_variant_product_needs_a_variant_and_takes_variant_stock(): void
    {
        $zone = $this->zone();
        $product = $this->product(0, true);
        $variant = $this->variant($product, 2);

        [$order, $error] = AdminOrderCreator::create($this->payload(
            $zone,
            [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'unit_price' => 1000]],
        ));

        $this->assertNull($order);
        $this->assertStringContainsString('needs a variant', $error);

        [$order, $error] = AdminOrderCreator::create($this->payload(
            $zone,
            [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 2, 'unit_price' => 1000]],
        ));

        $this->assertNull($error);
        $this->assertSame(0, $variant->fresh()->stock);
        $this->assertSame('Size: 7', $order->items()->first()->variant_label);
    }

    public function test_a_discount_bigger_than_the_items_total_is_refused(): void
    {
        $zone = $this->zone();
        $product = $this->product(5);

        [$order, $error] = AdminOrderCreator::create($this->payload(
            $zone,
            [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'unit_price' => 1000]],
            ['discount' => 1500],
        ));

        $this->assertNull($order);
        $this->assertStringContainsString('discount', $error);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_a_wrong_phone_number_is_refused(): void
    {
        $zone = $this->zone();
        $product = $this->product(5);

        [$order, $error] = AdminOrderCreator::create($this->payload(
            $zone,
            [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'unit_price' => 1000]],
            ['phone' => '12345'],
        ));

        $this->assertNull($order);
        $this->assertStringContainsString('mobile number', $error);
    }

    public function test_cancelling_an_admin_order_puts_the_stock_back_once(): void
    {
        $zone = $this->zone();
        $product = $this->product(5);

        [$order] = AdminOrderCreator::create($this->payload(
            $zone,
            [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 2, 'unit_price' => 1000]],
        ));

        $this->assertSame(3, $product->fresh()->stock);

        $this->assertNull(OrderStock::changeStatus($order, 'cancelled'));
        $this->assertSame(5, $product->fresh()->stock);

        // cancelling again changes nothing
        $this->assertNull(OrderStock::changeStatus($order->fresh(), 'cancelled'));
        $this->assertSame(5, $product->fresh()->stock);
    }
}