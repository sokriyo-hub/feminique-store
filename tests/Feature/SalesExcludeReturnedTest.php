<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesExcludeReturnedTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $status, int $total): Order
    {
        return Order::create([
            'code' => Order::generateCode(),
            'customer_name' => 'Test Customer',
            'phone' => '01712345678',
            'address' => 'House 1, Road 1, Dhaka',
            'subtotal' => $total,
            'delivery_charge' => 0,
            'total' => $total,
            'status' => $status,
        ]);
    }

    public function test_cancelled_and_returned_orders_are_not_counted_as_sales(): void
    {
        $this->order('pending', 100);
        $this->order('delivered', 200);
        $this->order('cancelled', 400);
        $this->order('returned', 800);

        $this->assertSame(300.0, (float) Order::counted()->sum('total'));
        $this->assertSame(2, Order::counted()->count());
    }
}