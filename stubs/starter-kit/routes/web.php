<?php

use App\Http\Controllers\AddProductToCartController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RemoveCartItemController;
use App\Http\Controllers\UpdateCartItemController;
use Illuminate\Support\Facades\Route;
use Larasell\Larasell\Routing\ProductDetailRoute;
use Larasell\Larasell\Routing\ProductListingRoute;

Route::inertia('/', 'Home')->name('home');

Route::get('/orders/{publicId}/confirmation', [OrderController::class, 'show'])
    ->name('orders.confirmation');

Route::get('/cart', CartController::class)
    ->name('cart.show');

Route::post('/cart', AddProductToCartController::class)
    ->name('cart.store');

Route::patch('/cart/items/{cartItem}', UpdateCartItemController::class)
    ->whereNumber('cartItem')
    ->name('cart.items.update');

Route::delete('/cart/items/{cartItem}', RemoveCartItemController::class)
    ->whereNumber('cartItem')
    ->name('cart.items.destroy');

ProductDetailRoute::get([ProductController::class, 'show'], prefix: 'p')
    ->name('products.show');

ProductListingRoute::get([ProductController::class, 'index'], prefix: 'c')
    ->name('products.index');
