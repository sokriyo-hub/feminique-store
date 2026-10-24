<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\Cart;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartController
{
    public function show(): View
    {
        // Stock কমে গেলে quantity নিজে থেকে ঠিক হয় এবং লাল বার্তা আসে
        $messages = Cart::reconcile();

        $lines = Cart::lines();

        return view('cart', [
            'lines' => $lines,
            'subtotal' => Cart::subtotal($lines),
        // 'max' => Cart::MAX_QTY, // TEMP (পুরনো view-এর জন্য), E2-তে যাবে
            'warnings' => array_merge((array) session('cart_warnings', []), $messages),
        ]);
    }

    public function add(Request $request): RedirectResponse
    {
        $product = Product::with(['variants', 'options'])->findOrFail($request->integer('product_id'));
        $want = max(1, $request->integer('quantity', 1));

        $variant = null;

        if ($product->has_variants) {
            $variant = $product->variants->firstWhere('id', $request->integer('variant_id'));

            if (! $variant) {
                $names = $product->options->pluck('name')->implode(', ');

                return back()->with('cart_notice', __('Please choose :options.', ['options' => $names]));
            }
        }

        $variantId = $variant?->id ?? 0;
        $stock = $product->stockFor($variant);
        $name = $variant ? $product->name . ' (' . $variant->label() . ')' : $product->name;

        if ($stock <= 0) {
            return back()->with('cart_notice', __(':name is sold out.', ['name' => $name]));
        }

        $inCart = Cart::quantityOf($product->id, $variantId);
        $room = $stock - $inCart;

        if ($room <= 0) {
            return back()->with('cart_notice', __(':name: all :stock available pieces are already in your cart.', [
                'name' => $name,
                'stock' => $stock,
            ]));
        }

        $add = min($want, $room);
        Cart::add($product->id, $variantId, $add);

        // Buy now: সরাসরি checkout-এ
        if ($request->boolean('buy_now')) {
            return redirect()->route('checkout');
        }

        $message = $add < $want
            ? __(':name: only :stock available, so your cart now has :stock.', ['name' => $name, 'stock' => $stock])
            : __(':name added to your cart.', ['name' => $name]);

        return back()->with('cart_notice', $message);
    }

    public function update(Request $request, string $line): RedirectResponse
    {
        $parsed = Cart::parseKey($line);

        if (! $parsed) {
            return redirect()->route('cart');
        }

        $key = Cart::key($parsed[0], $parsed[1]);
        $item = Cart::lines()->firstWhere('key', $key);

        if (! $item) {
            return redirect()->route('cart');
        }

        $quantity = $request->integer('quantity');

        if ($quantity <= 0) {
            Cart::remove($key);

            return redirect()->route('cart');
        }

        if ($item->stock <= 0) {
            Cart::remove($key);

            return redirect()->route('cart')->with('cart_warnings', [
                __(':name is sold out and was removed from your cart.', ['name' => Cart::lineName($item)]),
            ]);
        }

        if ($quantity > $item->stock) {
            Cart::set($key, $item->stock);

            return redirect()->route('cart')->with('cart_warnings', [
                __(':name: only :stock left, your cart was changed to :stock.', [
                    'name' => Cart::lineName($item),
                    'stock' => $item->stock,
                ]),
            ]);
        }

        Cart::set($key, $quantity);

        return redirect()->route('cart');
    }

    public function remove(string $line): RedirectResponse
    {
        $parsed = Cart::parseKey($line);

        if ($parsed) {
            Cart::remove(Cart::key($parsed[0], $parsed[1]));
        }

        return redirect()->route('cart');
    }
}