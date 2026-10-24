<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ChipPaymentController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PreorderController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');

Route::get('/pre-order', [PreorderController::class, 'create'])->name('preorder.create');
Route::post('/pre-order', [PreorderController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('preorder.store');

// Payment does not require a verified e-mail, so new buyers can pay at once.
Route::middleware('auth')->group(function () {
    Route::get('/pesanan/{order:reference}', [PreorderController::class, 'pay'])->name('preorder.pay');
    Route::post('/pesanan/{order:reference}/bayar', [ChipPaymentController::class, 'start'])
        ->middleware('throttle:10,1')
        ->name('payment.chip.start');
    Route::get('/pesanan/{order:reference}/selesai', [ChipPaymentController::class, 'back'])->name('payment.chip.back');
});

// Server-to-server from CHIP; CSRF-exempt in bootstrap/app.php, verified by signature.
Route::post('/webhooks/chip', [ChipPaymentController::class, 'callback'])
    ->middleware('throttle:120,1')
    ->name('payment.chip.callback');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/akaun', [AccountController::class, 'show'])->name('account');
});
