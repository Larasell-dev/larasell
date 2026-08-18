<?php

use Illuminate\Support\Facades\Route;
use Larasell\Larasell\Admin\Http\Authenticate as AdminAuthenticate;
use Larasell\Larasell\Admin\Http\Controllers\HomeController;
use Larasell\Larasell\Admin\Http\Controllers\LoginController;
use Larasell\Larasell\Admin\Http\Controllers\ProductController;
use Larasell\Larasell\Admin\Http\RedirectIfAuthenticated as AdminGuest;

$guard = config('larasell-admin.guard', 'larasell-admin');

Route::middleware(AdminGuest::using($guard))->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});

Route::middleware(AdminAuthenticate::using($guard))->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('products/{adminProduct}', [ProductController::class, 'show'])->name('products.show');
    Route::patch('products/{adminProduct}', [ProductController::class, 'update'])->name('products.update');
    Route::patch('products/{adminProduct}/general', [ProductController::class, 'updateGeneral'])->name('products.general.update');
    Route::patch('products/{adminProduct}/stock', [ProductController::class, 'updateStock'])->name('products.stock.update');
    Route::post('products/{adminProduct}/images', [ProductController::class, 'storeImage'])->name('products.images.store');
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
});
