<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class StoreController
{
    public function home(): View
    {
        return view('home', [
            'heroPaths' => Setting::heroImages(),
            'categories' => Category::withCount('products')->orderBy('sort_order')->orderBy('name')->get(),
            'newArrivals' => Product::with('images')->latest()->orderByDesc('id')->take(8)->get(),
        ]);
    }

    public function category(string $slug): View
    {
        $category = Category::where('slug', $slug)->firstOrFail();

        $products = $category->products()
            ->with('images')
            ->latest()
            ->orderByDesc('id')
            ->paginate(12);

        return view('category', compact('category', 'products'));
    }

    public function product(string $slug): View
    {
        $product = Product::with(['images', 'category'])->where('slug', $slug)->firstOrFail();

        return view('product', compact('product'));
    }

    public function page(string $slug): View
    {
        $page = Page::where('slug', $slug)->firstOrFail();

        return view('page', compact('page'));
    }

    public function wishlist(): View
    {
        return view('wishlist');
    }

    // Returns only the product cards (HTML) for the ids saved in the visitor's browser
    public function wishlistItems(Request $request): View
    {
        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn ($value) => (int) $value)
            ->filter(fn ($value) => $value > 0)
            ->unique()
            ->take(100)
            ->values();

        $products = Product::with('images')
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn ($product) => $ids->search($product->id))
            ->values();

        return view('wishlist-items', compact('products'));
    }
}