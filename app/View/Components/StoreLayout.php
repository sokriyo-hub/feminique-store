<?php

namespace App\View\Components;

use App\Models\Category;
use App\Models\Page;
use App\Models\Setting;
use App\Support\Cart;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class StoreLayout extends Component
{
    public Collection $categories;

    public Collection $policyPages;

    public array $settings;

    public int $cartCount;

    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public bool $back = false,
        public ?string $backUrl = null,
    ) {
        $this->categories = Category::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug']);
        $this->policyPages = Page::orderBy('id')->get(['id', 'title', 'slug']);
        $this->settings = Setting::pluck('value', 'name')->all();
        $this->cartCount = Cart::count();
    }

    public function render(): View
    {
        return view('components.store-layout');
    }
}