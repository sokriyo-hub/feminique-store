<?php

namespace Tests\Feature;

use App\Models\BlockedPhone;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Support\AdminOrderCreator;
use App\Support\CampaignPricing;
use App\Support\OrderStock;
use App\Support\ReturnGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnedOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CampaignPricing::flush();
    }

    private function product(int $stock = 5): Product
    {
        $category = Category::firstOrCreate(['slug' => 'rings'], ['name' => 'Rings', 'sort_order' => 1]);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Return Ring',
            'slug' => 'return-ring-' . uniqid(),
            'price' => 1000,
            'has_variants' => false,
            'stock' => $stock,
        ]);
    }

    // An order of 2 pieces (stock 5 -> 3)
    private function order(Product $product): Order
    {
        $zone = DeliveryZone::create(['name' => 'Inside Dhaka', 'charge' => 80, 'is_active' => true]);

        [$order, $error] = AdminOrderCreator::create([
            'name' => 'Test Customer',
            'phone' => '01712345678',
            'address' => 'House 1, Road 2, Dhaka',
            'source' => 'phone',
            'status' => 'confirmed',
            'zone_id' => $zone->id,
            'discount' => 0,
            'lines' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 2, 'unit_price' => 1000]],
        ]);

        $this->assertNull($error);

        return $order;
    }

    private function returnedOrder(string $phone, ?string $reason = 'refused'): Order
    {
        return Order::create([
            'code' => Order::generateCode(),
            'customer_name' => 'Refuser',
            'phone' => $phone,
            'address' => 'Dhaka',
            'subtotal' => 100,
            'total' => 100,
            'status' => 'returned',
            'return_reason' => $reason,
        ]);
    }

    public function test_returned_with_restock_puts_the_pieces_back(): void
    {
        $product = $this->product(5);
        $order = $this->order($product);
        $this->assertSame(3, $product->fresh()->stock);

        $this->assertNull(OrderStock::changeStatus($order, 'returned', true, 'refused'));

        $this->assertSame(5, $product->fresh()->stock);
        $this->assertFalse($order->fresh()->stock_deducted);
        $this->assertSame('returned', $order->fresh()->status);
    }

    public function test_returned_without_restock_keeps_the_stock_taken(): void
    {
        $product = $this->product(5);
        $order = $this->order($product);

        $this->assertNull(OrderStock::changeStatus($order, 'returned', false, 'damaged'));

        $this->assertSame(3, $product->fresh()->stock);
        $this->assertTrue($order->fresh()->stock_deducted);

        // opening it again must NOT take stock a second time
        $this->assertNull(OrderStock::changeStatus($order->fresh(), 'delivered'));
        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_returned_needs_a_stock_choice(): void
    {
        $product = $this->product(5);
        $order = $this->order($product);

        $error = OrderStock::changeStatus($order, 'returned', null, 'refused');

        $this->assertNotNull($error);
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_returned_needs_a_reason(): void
    {
        $product = $this->product(5);
        $order = $this->order($product);

        $this->assertStringContainsString('why', (string) OrderStock::changeStatus($order, 'returned', true));
        $this->assertStringContainsString('why', (string) OrderStock::changeStatus($order, 'returned', true, 'not-a-reason'));

        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_the_reason_note_and_date_are_saved_and_cleared_when_it_is_not_a_return_any_more(): void
    {
        $product = $this->product(5);
        $order = $this->order($product);

        OrderStock::changeStatus($order, 'returned', true, 'damaged', 'Chain was broken');

        $fresh = $order->fresh();
        $this->assertSame('damaged', $fresh->return_reason);
        $this->assertSame('Chain was broken', $fresh->return_note);
        $this->assertNotNull($fresh->returned_at);

        OrderStock::changeStatus($fresh, 'delivered');

        $fresh = $order->fresh();
        $this->assertNull($fresh->return_reason);
        $this->assertNull($fresh->return_note);
        $this->assertNull($fresh->returned_at);
    }

    public function test_a_restocked_return_takes_stock_again_when_reopened_and_is_blocked_if_short(): void
    {
        $product = $this->product(5);
        $order = $this->order($product);

        OrderStock::changeStatus($order, 'returned', true, 'refused');
        $this->assertSame(5, $product->fresh()->stock);

        $this->assertNull(OrderStock::changeStatus($order->fresh(), 'shipped'));
        $this->assertSame(3, $product->fresh()->stock);

        // return again, then somebody else buys the pieces: re-opening is refused
        OrderStock::changeStatus($order->fresh(), 'returned', true, 'refused');
        $product->update(['stock' => 1]);

        $error = OrderStock::changeStatus($order->fresh(), 'shipped');

        $this->assertNotNull($error);
        $this->assertStringContainsString('Not enough stock', $error);
        $this->assertSame('returned', $order->fresh()->status);
        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_moving_between_cancelled_and_returned_does_not_touch_stock(): void
    {
        $product = $this->product(5);
        $order = $this->order($product);

        OrderStock::changeStatus($order, 'cancelled');
        $this->assertSame(5, $product->fresh()->stock);

        $this->assertNull(OrderStock::changeStatus($order->fresh(), 'returned', null, 'refused'));
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertFalse($order->fresh()->stock_deducted);
    }

    public function test_only_the_customers_side_reasons_count_toward_the_red_flag(): void
    {
        $phone = '01755555555';

        $this->returnedOrder($phone, 'damaged');
        $this->returnedOrder($phone, 'wrong_item');
        $this->returnedOrder($phone, 'courier_problem');

        // three returns, but none is the customer's fault
        $this->assertSame(0, ReturnGuard::returnCount($phone));
        $this->assertFalse(ReturnGuard::isFlagged($phone));
        $this->assertNull(ReturnGuard::warningFor($phone));
        $this->assertSame(0, Order::withCount('phoneReturns')->first()->phone_returns_count);

        $this->returnedOrder($phone, 'refused');
        $this->returnedOrder($phone, 'unreachable');
        $this->returnedOrder($phone, 'fake_order');

        $this->assertSame(3, ReturnGuard::returnCount($phone));
        $this->assertTrue(ReturnGuard::isFlagged($phone));

        $history = ReturnGuard::history($phone);
        $this->assertSame(6, $history['orders']);
        $this->assertSame(6, $history['returned']);
        $this->assertSame(3, $history['counted']);
        $this->assertSame(3, $history['not_counted']);
        $this->assertSame(300.0, $history['counted_value']);
    }

    public function test_a_return_with_no_recorded_reason_counts(): void
    {
        $this->returnedOrder('01766666666', null);

        $this->assertSame(1, ReturnGuard::returnCount('01766666666'));
        $this->assertSame('Not recorded', Order::returnReasonLabel(null));
    }

    public function test_the_red_flag_follows_the_limit_in_settings(): void
    {
        $this->returnedOrder('01711111111');
        $this->returnedOrder('01711111111');

        // default limit is 3
        $this->assertSame(3, ReturnGuard::limit());
        $this->assertSame(2, ReturnGuard::returnCount('01711111111'));
        $this->assertFalse(ReturnGuard::isFlagged('01711111111'));

        Setting::put('return_flag_limit', '2');
        $this->assertTrue(ReturnGuard::isFlagged('01711111111'));

        Setting::put('return_flag_limit', '1');
        $this->assertTrue(ReturnGuard::isFlagged('01711111111'));
        $this->assertFalse(ReturnGuard::isFlagged('01722222222'));

        // empty or wrong value falls back to 3
        Setting::put('return_flag_limit', null);
        $this->assertSame(3, ReturnGuard::limit());
    }

    public function test_a_blocked_number_is_stopped_at_checkout_but_only_warned_in_the_order_desk(): void
    {
        $this->assertNull(ReturnGuard::checkoutMessage('01733333333'));
        $this->assertNull(ReturnGuard::warningFor('01733333333'));

        ReturnGuard::block('01733333333', 'Refused 3 parcels');

        $this->assertNotNull(ReturnGuard::checkoutMessage('01733333333'));
        $this->assertStringNotContainsString('Refused', (string) ReturnGuard::checkoutMessage('01733333333'));

        $warning = ReturnGuard::warningFor('+880 1733-333333');
        $this->assertStringContainsString('BLOCKED', (string) $warning);
        $this->assertStringContainsString('Refused 3 parcels', (string) $warning);

        ReturnGuard::unblock('01733333333');
        $this->assertNull(ReturnGuard::checkoutMessage('01733333333'));
    }

    public function test_the_history_of_a_blocked_number_lists_all_its_orders(): void
    {
        $blocked = ReturnGuard::block('01777777777', 'Fake orders');

        $this->returnedOrder('01777777777', 'refused');
        $this->returnedOrder('01777777777', 'damaged');
        $this->returnedOrder('01788888888', 'refused'); // another number

        $this->assertCount(2, $blocked->orders);
        $this->assertTrue(BlockedPhone::whereKey($blocked->id)->exists());
    }

    public function test_a_flagged_number_gets_a_warning_in_the_order_desk(): void
    {
        Setting::put('return_flag_limit', '2');
        $this->returnedOrder('01744444444');
        $this->returnedOrder('01744444444');

        $this->assertStringContainsString('2 returned', (string) ReturnGuard::warningFor('01744444444'));
        $this->assertNull(ReturnGuard::warningFor('not a number'));
    }
}