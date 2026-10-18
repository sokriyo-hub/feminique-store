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
        $lines = Cart::lines();

        return view('cart', [
            'lines' => $lines,
            'subtotal' => Cart::subtotal($lines),
            'max' => Cart::MAX_QTY,
        ]);
    }

    public function add(Request $request): RedirectResponse
    {
        $product = Product::findOrFail($request->integer('product_id'));

        $quantity = max(1, min(Cart::MAX_QTY, $request->integer('quantity', 1)));
        $capped = Cart::add($product->id, $quantity);

        // Buy now: সরাসরি checkout-এ
        if ($request->boolean('buy_now')) {
            return redirect()->route('checkout');
        }

        $message = $capped
            ? __('Maximum :max per item.', ['max' => Cart::MAX_QTY])
            : __(':name added to your cart.', ['name' => $product->name]);

        return back()->with('cart_notice', $message);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        Cart::set($product->id, $request->integer('quantity'));

        return redirect()->route('cart');
    }

    public function remove(Product $product): RedirectResponse
    {
        Cart::remove($product->id);

        return redirect()->route('cart');
    }
}