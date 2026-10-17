<?php

namespace Database\Seeders;

use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Seeder;

// শুধু demo/টেস্টের জন্য। Production-এ চালাবেন না। সব তথ্য নকল।
class DemoOrderSeeder extends Seeder
{
    public function run(): void
    {
        if (Order::where('phone', 'like', '0170000000%')->exists()) {
            $this->command?->info('Demo orders already exist. Skipped.');

            return;
        }

        $products = Product::orderBy('id')->get();

        if ($products->count() < 4) {
            $this->command?->warn('Not enough products. Run StoreSeeder first.');

            return;
        }

        $zone = DeliveryZone::where('is_active', true)->first();
        $charge = (float) ($zone?->charge ?? 0);

        // [নাম, ফোন, ঠিকানা, status, কত দিন আগে]
        $rows = [
            ['Demo Customer One', '01700000001', 'House 1, Road 1, Dhanmondi, Dhaka', 'pending', 0],
            ['Demo Customer Two', '01700000002', 'Flat 2B, Road 5, Gulshan, Dhaka', 'pending', 0],
            ['Demo Customer Three', '01700000003', 'House 7, Sector 4, Uttara, Dhaka', 'pending', 1],
            ['Demo Customer Four', '01700000004', 'Road 12, Mirpur 10, Dhaka', 'confirmed', 2],
            ['Demo Customer Five', '01700000005', 'House 3, Banani, Dhaka', 'confirmed', 3],
            ['Demo Customer Six', '01700000006', 'Lane 4, Mohammadpur, Dhaka', 'shipped', 4],
            ['Demo Customer Seven', '01700000007', 'House 9, Rampura, Dhaka', 'delivered', 6],
            ['Demo Customer Eight', '01700000008', 'Flat 5A, Badda, Dhaka', 'cancelled', 7],
        ];

        foreach ($rows as $i => [$name, $phone, $address, $status, $daysAgo]) {
            $picked = $products->slice($i, 2 + ($i % 2));

            $order = Order::create([
                'code' => Order::generateCode(),
                'customer_name' => $name,
                'phone' => $phone,
                'email' => $i === 0 ? 'demo@example.com' : null,
                'address' => $address,
                'delivery_zone_name' => $zone?->name,
                'subtotal' => 0,
                'delivery_charge' => $charge,
                'total' => 0,
                'status' => $status,
            ]);

            $subtotal = 0;

            foreach ($picked as $product) {
                $qty = 1 + ($i % 3);
                $unit = $product->selling_price;
                $line = $unit * $qty;
                $subtotal += $line;

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'regular_price' => $product->price,
                    'unit_price' => $unit,
                    'quantity' => $qty,
                    'line_total' => $line,
                ]);
            }

            $order->forceFill([
                'subtotal' => $subtotal,
                'total' => $subtotal + $charge,
                'created_at' => now()->subDays($daysAgo)->subMinutes($i * 7),
                'updated_at' => now()->subDays($daysAgo)->subMinutes($i * 7),
            ])->save();
        }

        $this->command?->info('8 demo orders created.');
    }
}