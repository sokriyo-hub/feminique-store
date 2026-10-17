<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Contracts\View\View;

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
}