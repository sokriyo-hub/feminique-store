<?php

use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StoreController::class, 'home'])->name('home');

Route::get('/category/{slug}', [StoreController::class, 'category'])
    ->where('slug', '[A-Za-z0-9_-]+')
    ->name('category.show');

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