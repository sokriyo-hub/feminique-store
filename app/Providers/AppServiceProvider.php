<?php

namespace App\Providers;

use App\View\Components\StoreLayout;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
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

        // Behind Tailscale Funnel (or any HTTPS proxy) the app itself sees plain HTTP.
        // When APP_URL starts with https://, build every link with https:// too.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}