<?php

namespace App\Providers;

use App\View\Components\StoreLayout;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Make sure <x-store-layout> always uses the StoreLayout class
        // (it provides $settings, $categories, $policyPages, $cartCount)
        Blade::component('store-layout', StoreLayout::class);
    }
}