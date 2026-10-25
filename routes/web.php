<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StoreController::class, 'home'])->name('home');

Route::get('/category/{slug}', [StoreController::class, 'category'])
    ->where('slug', '[A-Za-z0-9_-]+')
    ->name('category.show');

Route::get('/offers/{slug}', [StoreController::class, 'offer'])
    ->where('slug', '[A-Za-z0-9_-]+')
    ->name('offer.show');
    
Route::get('/product/{slug}', [StoreController::class, 'product'])
    ->where('slug', '[A-Za-z0-9_-]+')
    ->name('product.show');

Route::get('/pages/{slug}', [StoreController::class, 'page'])
    ->where('slug', '[A-Za-z0-9_-]+')
    ->name('page.show');

Route::get('/search', [StoreController::class, 'search'])
    ->middleware('throttle:60,1')
    ->name('search');

Route::get('/wishlist', [StoreController::class, 'wishlist'])->name('wishlist');

Route::get('/wishlist/items', [StoreController::class, 'wishlistItems'])
    ->middleware('throttle:60,1')
    ->name('wishlist.items');

Route::get('/cart', [CartController::class, 'show'])->name('cart');

Route::post('/cart/add', [CartController::class, 'add'])
    ->middleware('throttle:60,1')
    ->name('cart.add');

// {line} = "productId-variantId" (যেমন 12-3). শুধু "12" দিলে variant ছাড়া product ধরা হয়।
Route::post('/cart/{line}/update', [CartController::class, 'update'])
    ->where('line', '[0-9]+(-[0-9]+)?')
    ->middleware('throttle:60,1')
    ->name('cart.update');

Route::post('/cart/{line}/remove', [CartController::class, 'remove'])
    ->where('line', '[0-9]+(-[0-9]+)?')
    ->middleware('throttle:60,1')
    ->name('cart.remove');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');

Route::post('/checkout', [CheckoutController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('checkout.store');

Route::get('/order-placed', [CheckoutController::class, 'placed'])->name('order.placed');

Route::get('/track', [TrackingController::class, 'form'])->name('track');

Route::post('/track', [TrackingController::class, 'lookup'])
    ->middleware('throttle:30,1')
    ->name('track.lookup');