<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;
use Larasell\Larasell\Routing\ProductDetailRoute;
use Larasell\Larasell\Routing\ProductListingRoute;

Route::inertia('/', 'Home')->name('home');

Route::get('/orders/{publicId}/confirmation', [OrderController::class, 'show'])
    ->name('orders.confirmation');

ProductListingRoute::get([ProductController::class, 'index'], prefix: 'c')
    ->name('products.index');

ProductDetailRoute::get([ProductController::class, 'show'], prefix: 'p')
    ->name('products.show');
