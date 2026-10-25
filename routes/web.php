<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\PageImageController;
use App\Http\Controllers\CatalogController;
use App\Http\Middleware\EnsureCatalogOpen;
use App\Http\Controllers\ChildController;
use App\Http\Controllers\ChildModeController;
use App\Http\Controllers\ChildQuizController;
use App\Http\Controllers\ChipPaymentController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Teacher;
use App\Http\Middleware\EnsureInstitutionStaff;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\ParentPinController;
use App\Http\Controllers\PassPageController;
use App\Http\Controllers\PilotRequestController;
use App\Http\Controllers\PreorderController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReaderController;
use App\Http\Controllers\ReadingProgressController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\WeeklyReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');
Route::get('/privasi', [LegalController::class, 'privacy'])->name('privacy');
Route::get('/terma', [LegalController::class, 'terms'])->name('terms');

Route::get('/pas', PassPageController::class)->name('pass.show');

// Regular yearly pass once the early bird slots are gone (redirects to the pre-order before that).
Route::get('/beli/pas', [PurchaseController::class, 'pass'])->name('purchase.pass');
Route::post('/beli/pas', [PurchaseController::class, 'storePass'])
    ->middleware('throttle:10,1')
    ->name('purchase.pass.store');

// Phase B: hidden (404) until the admin opens the catalogue; staff can always preview.
Route::middleware(EnsureCatalogOpen::class)->group(function () {
    Route::get('/buku', [CatalogController::class, 'index'])->name('catalog.index');
    Route::get('/buku/{book:slug}', [CatalogController::class, 'show'])->name('catalog.show');
    // Free preview of the first pages, no login needed.
    Route::get('/buku/{book:slug}/pratonton', [PreviewController::class, 'show'])->name('catalog.preview');
    Route::get('/buku/{book:slug}/pratonton/{page}', [PreviewController::class, 'page'])
        ->whereNumber('page')
        ->middleware(['signed', 'throttle:120,1'])
        ->name('catalog.preview.page');

    Route::get('/beli/buku/{book:slug}', [PurchaseController::class, 'book'])->name('purchase.book');
    Route::post('/beli/buku/{book:slug}', [PurchaseController::class, 'storeBook'])
        ->middleware('throttle:10,1')
        ->name('purchase.book.store');

    Route::middleware('auth')->group(function () {
        Route::get('/baca/{book:slug}', [ReaderController::class, 'show'])->name('reader.show');
        Route::get('/baca/{book:slug}/fail/{file}', [ReaderController::class, 'file'])
            ->middleware(['signed', 'throttle:180,1']) // the reader fetches the PDF in pieces
            ->name('reader.file');
        Route::get('/baca/{book:slug}/halaman/{file}/{page}', [ReaderController::class, 'page'])
            ->whereNumber('page')
            ->middleware(['signed', 'throttle:600,1'])
            ->name('reader.page');
    });
});

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

    // Staff only (checked in the controller): "versi pantas" page images made in the admin's browser.
    Route::prefix('/admin/versi-pantas/{file}')->name('admin.pages.')->controller(PageImageController::class)->group(function () {
        Route::get('/pdf', 'pdf')->name('pdf');
        Route::post('/mula', 'start')->name('start');
        Route::post('/halaman/{page}', 'store')->whereNumber('page')->middleware('throttle:600,1')->name('store');
        Route::post('/siap', 'finish')->name('finish');
    });
});

// Server-to-server from CHIP; CSRF-exempt in bootstrap/app.php, verified by signature.
Route::post('/webhooks/chip', [ChipPaymentController::class, 'callback'])
    ->middleware('throttle:120,1')
    ->name('payment.chip.callback');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/akaun', [AccountController::class, 'show'])->name('account');

    // Phase C: child profiles are only useful with the catalogue, so they open with it.
    Route::middleware(EnsureCatalogOpen::class)->prefix('/akaun/anak')->name('children.')->controller(ChildController::class)->group(function () {
        Route::get('/tambah', 'create')->name('create');
        Route::post('/', 'store')->middleware('throttle:20,1')->name('store');
        Route::get('/{child}/ubah', 'edit')->name('edit');
        Route::put('/{child}', 'update')->name('update');
        Route::delete('/{child}', 'destroy')->name('destroy');
    });

    Route::middleware(EnsureCatalogOpen::class)->group(function () {
        Route::post('/akaun/anak/{child}/mod-anak', [ChildModeController::class, 'enter'])->name('children.child-mode');
        Route::put('/akaun/laporan-mingguan', [WeeklyReportController::class, 'update'])->name('weekly-report.update');
        Route::put('/akaun/pin', [ParentPinController::class, 'update'])->middleware('throttle:10,1')->name('parent-pin.update');
        Route::delete('/akaun/pin', [ParentPinController::class, 'destroy'])->middleware('throttle:10,1')->name('parent-pin.destroy');

        // Mod Anak (LockChildMode keeps the child here until the parent gate is passed).
        Route::prefix('/mod-anak')->name('child-mode.')->controller(ChildModeController::class)->group(function () {
            Route::get('/', 'home')->name('home');
            Route::get('/keluar', 'gate')->name('gate');
            Route::post('/keluar', 'leave')->middleware('throttle:10,1')->name('leave');
            Route::get('/lencana', 'badges')->name('badges');
        });
        Route::prefix('/mod-anak/kuiz/{book:slug}')->name('child-mode.quiz.')->controller(ChildQuizController::class)->group(function () {
            Route::get('/', 'show')->name('show');
            Route::post('/', 'submit')->middleware('throttle:20,1')->name('submit');
            Route::get('/keputusan', 'result')->name('result');
        });
        Route::post('/mod-anak/bacaan/{book:slug}', [ReadingProgressController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('child-mode.progress');
    });
});

// Link in the weekly report e-mail; signed so it works without logging in.
Route::get('/laporan-mingguan/berhenti/{user}', [WeeklyReportController::class, 'unsubscribe'])
    ->middleware(['signed', 'throttle:10,1'])
    ->name('weekly-report.unsubscribe');

// Phase D: institution invitations and the teacher portal.
Route::get('/jemputan/{token}', [InvitationController::class, 'show'])->middleware('throttle:30,1')->name('invitation.show');
Route::post('/jemputan/{token}', [InvitationController::class, 'accept'])->middleware('throttle:10,1')->name('invitation.accept');

Route::middleware(['auth', 'verified', EnsureInstitutionStaff::class])->prefix('/guru')->name('teacher.')->group(function () {
    Route::get('/', Teacher\HomeController::class)->name('home');
});
