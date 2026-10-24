<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ChipPaymentController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\PilotRequestController;
use App\Http\Controllers\PreorderController;
use App\Http\Controllers\ReceiptController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');
Route::get('/privasi', [LegalController::class, 'privacy'])->name('privacy');
Route::get('/terma', [LegalController::class, 'terms'])->name('terms');

Route::get('/pilot', [PilotRequestController::class, 'create'])->name('pilot.create');
Route::post('/pilot', [PilotRequestController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('pilot.store');
Route::get('/pilot/terima-kasih', [PilotRequestController::class, 'thanks'])->name('pilot.thanks');

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

    Route::get('/pesanan/{order:reference}/resit', [ReceiptController::class, 'create'])->name('preorder.receipt');
    Route::post('/pesanan/{order:reference}/resit', [ReceiptController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('preorder.receipt.store');

    // Admin only (checked in the controller); used by the Filament order page.
    Route::get('/admin/resit/{order:reference}', [ReceiptController::class, 'show'])->name('admin.receipt');
});

// Server-to-server from CHIP; CSRF-exempt in bootstrap/app.php, verified by signature.
Route::post('/webhooks/chip', [ChipPaymentController::class, 'callback'])
    ->middleware('throttle:120,1')
    ->name('payment.chip.callback');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/akaun', [AccountController::class, 'show'])->name('account');
});
