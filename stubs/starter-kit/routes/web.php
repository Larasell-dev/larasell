<?php

use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Home')->name('home');

Route::get('/orders/{publicId}/confirmation', [OrderController::class, 'show'])
    ->name('orders.confirmation');
