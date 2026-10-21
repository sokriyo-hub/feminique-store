<?php

namespace App\Http\Controllers;

use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Cart;
use App\Support\Delivery;
use App\Support\Phone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CheckoutController
{
    public function show(): View|RedirectResponse
    {
        // Stock কমে গেলে: quantity ঠিক করে cart পেজে ফেরত, সাথে লাল বার্তা
        $messages = Cart::reconcile();

        if ($messages) {
            return redirect()->route('cart')->with('cart_warnings', $messages);
        }

        $lines = Cart::lines();

        if ($lines->isEmpty()) {
            return redirect()->route('cart');
        }

        $subtotal = Cart::subtotal($lines);
        $zones = DeliveryZone::where('is_active', true)->orderBy('id')->get();

        $zoneData = $zones->map(fn (DeliveryZone $zone) => [
            'id' => $zone->id,
            'name' => $zone->name,
            'charge' => Delivery::charge($zone, $subtotal),
        ])->values()->all();

        return view('checkout', [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'zones' => $zones,
            'zoneData' => $zoneData,
            'freeFrom' => Delivery::freeThreshold(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // লুকানো ফাঁদ-ঘর: মানুষ এটা পূরণ করে না, bot করে
        if (filled($request->input('website'))) {
            return redirect()->route('home');
        }

        if (Cart::count() === 0) {
            return redirect()->route('cart');
        }

        $zones = DeliveryZone::where('is_active', true)->orderBy('id')->get();

        if ($zones->isEmpty()) {
            return redirect()->route('checkout');
        }

        // ফোন নম্বর 01XXXXXXXXX রূপে এনে যাচাই
        $normalized = Phone::normalize((string) $request->input('phone'));

        if ($normalized !== null) {
            $request->merge(['phone' => $normalized]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'regex:/^01[3-9][0-9]{8}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['required', 'string', 'min:5', 'max:500'],
            'zone_id' => [Rule::requiredIf($zones->count() > 1), 'nullable', 'integer', Rule::in($zones->pluck('id')->all())],
        ], [
            'phone.regex' => __('Enter a valid mobile number, like 01712345678.'),
        ]);

        $zone = $zones->count() === 1
            ? $zones->first()
            : $zones->firstWhere('id', (int) $data['zone_id']);

        /*
         * Transaction: stock সারিগুলো lock করে আবার যাচাই, তারপর order বানানো ও stock কমানো।
         * ফেরত: Order (সফল) | false (stock কম) | null (cart খালি)
         */
        $order = DB::transaction(function () use ($zone, $data) {
            $parsed = [];

            foreach (Cart::raw() as $key => $quantity) {
                $ids = Cart::parseKey($key);

                if ($ids) {
                    $parsed[] = [$ids[0], $ids[1], $quantity];
                }
            }

            if (! $parsed) {
                return null;
            }

            // lock সবসময় একই ক্রমে (id অনুযায়ী), যাতে দুই order একে অপরকে আটকে না দেয়
            $products = Product::whereIn('id', array_unique(array_column($parsed, 0)))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $variantIds = array_values(array_filter(array_column($parsed, 1)));

            $variants = $variantIds
                ? ProductVariant::whereIn('id', $variantIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id')
                : collect();

            $rows = [];

            foreach ($parsed as [$productId, $variantId, $quantity]) {
                $product = $products->get($productId);

                if (! $product) {
                    continue;
                }

                $variant = null;

                if ($product->has_variants) {
                    $variant = $variants->get($variantId);

                    if (! $variant || (int) $variant->product_id !== (int) $product->id) {
                        continue;
                    }
                } elseif ($variantId !== 0) {
                    continue;
                }

                if ($product->stockFor($variant) < $quantity) {
                    return false; // কিছুই লেখা হয়নি, তাই নিরাপদে বেরিয়ে যাওয়া যায়
                }

                [$regular, $unit] = $product->linePrices($variant);

                $rows[] = [
                    'product' => $product,
                    'variant' => $variant,
                    'quantity' => $quantity,
                    'regular' => $regular,
                    'unit' => $unit,
                    'total' => round($unit * $quantity, 2),
                ];
            }

            if (! $rows) {
                return null;
            }

            $subtotal = round((float) array_sum(array_column($rows, 'total')), 2);
            $charge = Delivery::charge($zone, $subtotal);

            $order = Order::create([
                'code' => Order::generateCode(),
                'customer_name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'address' => $data['address'],
                'delivery_zone_name' => $zone->name,
                'subtotal' => $subtotal,
                'delivery_charge' => $charge,
                'total' => round($subtotal + $charge, 2),
                'status' => 'pending',
                'stock_deducted' => true,
            ]);

            foreach ($rows as $row) {
                $product = $row['product'];
                $variant = $row['variant'];

                // নাম, দাম, variant ও SKU-র snapshot: পরে product বদলালেও order বদলাবে না
                $order->items()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'product_name' => $product->name,
                    'variant_label' => $variant?->label(),
                    'sku' => $variant?->sku ?? $product->sku,
                    'regular_price' => $row['regular'],
                    'unit_price' => $row['unit'],
                    'quantity' => $row['quantity'],
                    'line_total' => $row['total'],
                ]);

                // stock কমানো
                if ($variant) {
                    $variant->decrement('stock', $row['quantity']);
                } else {
                    $product->decrement('stock', $row['quantity']);
                }
            }

            return $order;
        });

        if ($order === null) {
            return redirect()->route('cart');
        }

        // Stock কম পড়ে গেছে (অন্য কেউ আগে কিনেছে): quantity ঠিক করে cart-এ ফেরত
        if ($order === false) {
            $messages = Cart::reconcile();

            return redirect()->route('cart')->with('cart_warnings', $messages ?: [
                __('Stock has changed. Please review your cart.'),
            ]);
        }

        Cart::clear();
        session(['last_order_id' => $order->id]);

        return redirect()->route('order.placed');
    }

    // Confirmation: শুধু যে session থেকে order হয়েছে সেখানেই দেখা যায়
    public function placed(): View|RedirectResponse
    {
        $order = Order::with('items')->find(session('last_order_id'));

        if (! $order) {
            return redirect()->route('home');
        }

        return view('order-placed', compact('order'));
    }
}