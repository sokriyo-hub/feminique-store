<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Support\AdminOrderCreator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderLinesTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $stock): Product
    {
        $category = Category::create(['name' => 'Rings', 'slug' => 'rings', 'sort_order' => 1]);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Shared Ring',
            'slug' => 'shared-ring',
            'price' => 1000,
            'has_variants' => false,
            'stock' => $stock,
        ]);
    }

    private function data(Product $product, int $quantity): array
    {
        $zone = DeliveryZone::firstOrCreate(['name' => 'Inside Dhaka'], ['charge' => 80, 'is_active' => true]);

        return [
            'name' => 'Test Customer',
            'phone' => '01712345678',
            'address' => 'House 1, Road 1, Dhaka',
            'source' => 'phone',
            'status' => 'confirmed',
            'zone_id' => $zone->id,
            'lines' => [[
                'product_id' => $product->id,
                'variant_id' => 0,
                'quantity' => $quantity,
                'unit_price' => 1000,
            ]],
        ];
    }

    public function test_an_admin_order_takes_stock_through_the_shared_code(): void
    {
        $product = $this->product(5);

        [$order, $error] = AdminOrderCreator::create($this->data($product, 2));

        $this->assertNull($error);
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertCount(1, $order->items);
        $this->assertSame('Shared Ring', $order->items->first()->product_name);
    }

    public function test_not_enough_stock_saves_nothing_and_keeps_the_stock(): void
    {
        $product = $this->product(5);

        [$order, $error] = AdminOrderCreator::create($this->data($product, 9));

        $this->assertNull($order);
        $this->assertNotNull($error);
        $this->assertSame(0, Order::count());
        $this->assertSame(5, $product->fresh()->stock);
    }
}