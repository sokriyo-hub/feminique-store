<?php

namespace App\View\Components;

use App\Models\Campaign;
use App\Models\Page;
use App\Models\Setting;
use App\Support\CampaignPricing;
use App\Support\Cart;
use App\Support\CategoryTree;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class StoreLayout extends Component
{
    // Same limit as the "Show in navbar" rule in the admin category form
    public const NAVBAR_MAX = 6;

    // All main categories with their sub-categories (mobile menu)
    public array $menu;

    // Only the main categories chosen for the top menu (desktop)
    public array $navMenu;

    // The running campaign that has "Show in the top menu" on (null = no link)
    public ?Campaign $offer;

    public Collection $policyPages;

    public array $settings;

    public int $cartCount;

    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public bool $back = false,
        public ?string $backUrl = null,
        public bool $overlay = false, // true = menu sits on top of the hero image (home page)
    ) {
        $this->menu = CategoryTree::menu();
        $this->navMenu = array_slice(
            array_values(array_filter($this->menu, fn (array $node): bool => $node['navbar'])),
            0,
            self::NAVBAR_MAX
        );
        $this->offer = CampaignPricing::menuOffer();
        $this->policyPages = Page::orderBy('id')->get(['id', 'title', 'slug']);
        $this->settings = Setting::pluck('value', 'name')->all();
        $this->cartCount = Cart::count();
    }

    public function render(): View
    {
        return view('components.store-layout');
    }
}