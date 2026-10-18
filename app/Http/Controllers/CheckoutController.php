<?php

namespace App\Http\Controllers;

use App\Models\DeliveryZone;
use App\Models\Order;
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

        $lines = Cart::lines();

        if ($lines->isEmpty()) {
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

        $order = DB::transaction(function () use ($lines, $zone, $data) {
            $subtotal = Cart::subtotal($lines);
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
            ]);

            // নাম ও দামের snapshot, পরে product বদলালেও order বদলাবে না
            foreach ($lines as $line) {
                $order->items()->create([
                    'product_id' => $line->product->id,
                    'product_name' => $line->product->name,
                    'regular_price' => $line->product->price,
                    'unit_price' => $line->unit_price,
                    'quantity' => $line->quantity,
                    'line_total' => $line->line_total,
                ]);
            }

            return $order;
        });

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