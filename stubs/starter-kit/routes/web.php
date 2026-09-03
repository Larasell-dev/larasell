<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StorefrontLocaleController;
use App\Http\Middleware\SetStorefrontLocale;
use Illuminate\Support\Facades\Route;
use Larasell\Larasell\Routing\ProductListingRoute;

Route::middleware(SetStorefrontLocale::class)->group(function (): void {
    Route::inertia('/', 'Home')->name('home');

    Route::post('/locale', [StorefrontLocaleController::class, 'store'])
        ->name('locale.store');

    Route::get('/orders/{publicId}/confirmation', [OrderController::class, 'show'])
        ->name('orders.confirmation');

    ProductListingRoute::get([ProductController::class, 'index'], prefix: 'c')
        ->name('products.index');
});
