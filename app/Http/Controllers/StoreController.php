<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use App\Support\Popular;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class StoreController
{
    public function home(): View
    {
        $newArrivals = Product::with(['images', 'variants'])->latest()->orderByDesc('id')->take(8)->get();

        return view('home', [
            'hero' => Setting::heroContent(),
            'heroPaths' => Setting::heroImages(),
            'categories' => Category::topLevel()->withCount('products')->orderBy('sort_order')->orderBy('name')->get(),
            'newArrivals' => $newArrivals,
            // Best sellers of the last 90 days; filled with newest products that are not already in New Arrivals
            'popular' => Popular::products(null, 8, $newArrivals->pluck('id')->all()),
        ]);
    }

    public function category(string $slug): View
    {
        $category = Category::with('parent')->where('slug', $slug)->firstOrFail();

        // Sub-categories A-Z
        $children = $category->children()->get()
            ->sort(fn ($a, $b) => strnatcasecmp($a->name, $b->name))
            ->values();

        // A parent page shows the products of its whole branch (itself + everything below)
        $branchIds = $category->branchIds();

        $products = Product::with(['images', 'variants'])
            ->whereIn('category_id', $branchIds)
            ->latest()
            ->orderByDesc('id')
            ->paginate(12);

        // "Customer Favorites in ...": only on the first page, only when there are sub-categories,
        // and only when there are more products than the favorites row itself (else it would repeat the list)
        $popular = collect();

        if ($children->isNotEmpty() && $products->currentPage() === 1 && $products->total() > 4) {
            $popular = Popular::products($branchIds, 4);
        }

        $backUrl = $category->parent
            ? route('category.show', $category->parent->slug)
            : route('home');

        return view('category', compact('category', 'children', 'products', 'popular', 'backUrl'));
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

    // Search: every word must match the product name, description or category name
    public function search(Request $request): View
    {
        $raw = $request->query('q');
        $term = is_string($raw) ? mb_substr(trim(preg_replace('/\s+/u', ' ', $raw)), 0, 60) : '';

        $products = null;

        if (mb_strlen($term) >= 2) {
            $words = array_slice(explode(' ', $term), 0, 5);
            $phrase = '%' . addcslashes($term, '\\%_') . '%';

            $query = Product::with(['images', 'variants']);

            foreach ($words as $word) {
                $like = '%' . addcslashes($word, '\\%_') . '%';

                $query->where(function ($q) use ($like) {
                    $q->where('name', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $like));
                });
            }

            $products = $query
                ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', [$phrase])
                ->latest()
                ->orderByDesc('id')
                ->paginate(12)
                ->withQueryString();
        }

        return view('search', compact('term', 'products'));
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

        $products = Product::with(['images', 'variants'])
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn ($product) => $ids->search($product->id))
            ->values();

        return view('wishlist-items', compact('products'));
    }
}