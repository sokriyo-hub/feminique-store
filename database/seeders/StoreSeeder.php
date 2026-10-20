<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Database\Seeder;

// Creates demo data ONLY where it is missing. Safe to run again: it never overwrites admin changes.
class StoreSeeder extends Seeder
{
    public function run(): void
    {
        // ---------- Main categories ----------
        $categories = [
            ['name' => 'Rings', 'slug' => 'rings'],
            ['name' => 'Necklaces', 'slug' => 'necklaces'],
            ['name' => 'Earrings', 'slug' => 'earrings'],
            ['name' => 'Bracelets', 'slug' => 'bracelets'],
            ['name' => 'Hair Accessories', 'slug' => 'hair-accessories'],
            ['name' => 'Gift Sets', 'slug' => 'gift-sets'],
        ];

        foreach ($categories as $i => $c) {
            Category::firstOrCreate(
                ['slug' => $c['slug']],
                ['name' => $c['name'], 'sort_order' => $i + 1, 'show_in_navbar' => true]
            );
        }

        // ---------- Demo sub-categories (name, slug, parent slug) ----------
        $subs = [
            ['Gold Rings', 'gold-rings', 'rings'],
            ['Pearl Rings', 'pearl-rings', 'rings'],
            ['Chain Necklaces', 'chain-necklaces', 'necklaces'],
            ['Stud Earrings', 'stud-earrings', 'earrings'],
        ];

        foreach ($subs as $i => [$name, $slug, $parentSlug]) {
            $parent = Category::where('slug', $parentSlug)->first();

            if (! $parent) {
                continue;
            }

            Category::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'parent_id' => $parent->id, 'sort_order' => $i + 1, 'show_in_navbar' => false]
            );
        }

        // ---------- Dummy products (name, category slug, price, discount price) ----------
        $products = [
            ['Pearl Drop Ring', 'rings', 1200, 950],
            ['Golden Leaf Ring', 'rings', 900, null],
            ['Rose Quartz Necklace', 'necklaces', 2500, 1990],
            ['Layered Chain Necklace', 'necklaces', 1800, null],
            ['Pearl Stud Earrings', 'earrings', 750, 600],
            ['Crescent Moon Earrings', 'earrings', 950, null],
            ['Charm Bracelet', 'bracelets', 1500, 1200],
            ['Beaded Bracelet', 'bracelets', 650, null],
            ['Floral Hair Clip', 'hair-accessories', 450, 350],
            ['Satin Scrunchie Set', 'hair-accessories', 500, null],
            ['Elegance Gift Set', 'gift-sets', 3500, 2990],
            ['Bridal Jewellery Set', 'gift-sets', 6500, null],
            // inside sub-categories
            ['Gold Band Ring', 'gold-rings', 1400, 1150],
            ['Twisted Gold Ring', 'gold-rings', 1600, null],
            ['Freshwater Pearl Ring', 'pearl-rings', 1300, null],
            ['Thin Chain Necklace', 'chain-necklaces', 1100, 900],
            ['Mini Hoop Studs', 'stud-earrings', 550, null],
        ];

        foreach ($products as $p) {
            $category = Category::where('slug', $p[1])->first();
            $slug = str($p[0])->slug()->toString();

            if (! $category || Product::where('slug', $slug)->exists()) {
                continue;
            }

            Product::create([
                'category_id' => $category->id,
                'name' => $p[0],
                'slug' => $slug,
                'description' => 'This is a dummy description for ' . $p[0] . '. Real product details will be added by the admin.',
                'price' => $p[2],
                'discount_price' => $p[3],
                'stock' => 10,
            ]);
        }

        // ---------- Delivery zone ----------
        if (! DeliveryZone::where('name', 'Inside Dhaka')->exists()) {
            DeliveryZone::create(['name' => 'Inside Dhaka', 'charge' => 80, 'is_active' => true]);
        }

        // ---------- Pages ----------
        $pages = [
            ['Privacy Policy', 'privacy-policy'],
            ['Return & Exchange Policy', 'return-exchange-policy'],
            ['Terms & Conditions', 'terms-and-conditions'],
        ];

        foreach ($pages as $pg) {
            Page::firstOrCreate(
                ['slug' => $pg[1]],
                [
                    'title' => $pg[0],
                    'content' => '<p>This page is a placeholder. The content will be provided by the client.</p>',
                ]
            );
        }

        // ---------- Default settings ----------
        $settings = [
            'footer_address' => 'Dhaka, Bangladesh',
            'footer_phone' => '',
            'facebook_url' => '',
            'instagram_url' => '',
            'free_delivery_threshold' => '',
        ];

        foreach ($settings as $name => $value) {
            Setting::firstOrCreate(['name' => $name], ['value' => $value]);
        }
    }
}