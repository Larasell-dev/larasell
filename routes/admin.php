<?php

use Illuminate\Support\Facades\Route;
use Larasell\Larasell\Admin\Http\Authenticate as AdminAuthenticate;
use Larasell\Larasell\Admin\Http\Controllers\HomeController;
use Larasell\Larasell\Admin\Http\Controllers\LoginController;

$guard = config('larasell-admin.guard', 'larasell-admin');

Route::middleware("guest:$guard")->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});

Route::middleware(AdminAuthenticate::using($guard))->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
});
