<?php

use Illuminate\Support\Facades\Route;
use Larasell\Larasell\Admin\Http\Authenticate as AdminAuthenticate;
use Larasell\Larasell\Admin\Http\Controllers\HomeController;
use Larasell\Larasell\Admin\Http\Controllers\LoginController;
use Larasell\Larasell\Admin\Http\Controllers\MediaController;
use Larasell\Larasell\Admin\Http\Controllers\MediaUploadController;
use Larasell\Larasell\Admin\Http\Controllers\OrderController;
use Larasell\Larasell\Admin\Http\Controllers\ProductController;
use Larasell\Larasell\Admin\Http\Controllers\ProductOptionController;
use Larasell\Larasell\Admin\Http\Controllers\MemberController;
use Larasell\Larasell\Admin\Http\Controllers\SettingsController;
use Larasell\Larasell\Admin\Http\Controllers\CurrencySettingsController;
use Larasell\Larasell\Admin\Http\RedirectIfAuthenticated as AdminGuest;

$guard = config('larasell-admin.guard', 'larasell-admin');

Route::middleware(AdminGuest::using($guard))->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});

Route::middleware(AdminAuthenticate::using($guard))->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('settings', SettingsController::class)->name('settings.index');
    Route::get('settings/currencies', [CurrencySettingsController::class, 'index'])->name('settings.currencies.index');
    Route::patch('settings/currencies', [CurrencySettingsController::class, 'update'])->name('settings.currencies.update');
    Route::get('settings/members', [MemberController::class, 'index'])->name('settings.members.index');
    Route::get('settings/members/create', [MemberController::class, 'create'])->name('settings.members.create');
    Route::post('settings/members', [MemberController::class, 'store'])->name('settings.members.store');
    Route::get('settings/members/{adminMember}', [MemberController::class, 'show'])->name('settings.members.show');
    Route::patch('settings/members/{adminMember}', [MemberController::class, 'update'])->name('settings.members.update');
    Route::delete('settings/members/{adminMember}', [MemberController::class, 'destroy'])->name('settings.members.destroy');
    Route::get('media', [MediaController::class, 'index'])->name('media.index');
    Route::post('media/uploads', [MediaUploadController::class, 'store'])->name('media.uploads.store');
    Route::delete('media', [MediaController::class, 'destroy'])->name('media.destroy');
    Route::get('orders', OrderController::class)->name('orders.index');
    Route::get('orders/{adminOrder}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('products', [ProductController::class, 'store'])->name('products.store');
    Route::get('product-options', [ProductOptionController::class, 'index'])->name('product-options.index');
    Route::get('product-options/create', [ProductOptionController::class, 'create'])->name('product-options.create');
    Route::post('product-options', [ProductOptionController::class, 'store'])->name('product-options.store');
    Route::get('product-options/{adminProductOption}', [ProductOptionController::class, 'show'])->name('product-options.show');
    Route::patch('product-options/{adminProductOption}', [ProductOptionController::class, 'update'])->name('product-options.update');
    Route::delete('product-options/{adminProductOption}', [ProductOptionController::class, 'destroy'])->name('product-options.destroy');
    Route::get('products/{adminProduct}', [ProductController::class, 'show'])->name('products.show');
    Route::patch('products/{adminProduct}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('products/{adminProduct}', [ProductController::class, 'destroy'])->name('products.destroy');
    Route::patch('products/{adminProduct}/general', [ProductController::class, 'updateGeneral'])->name('products.general.update');
    Route::patch('products/{adminProduct}/stock', [ProductController::class, 'updateStock'])->name('products.stock.update');
    Route::post('products/{adminProduct}/images', [ProductController::class, 'storeImage'])->name('products.images.store');
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
});
