<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/akaun', [AccountController::class, 'show'])->name('account');
});
