<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Admin\PageImageController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ChildController;
use App\Http\Controllers\ChildGameController;
use App\Http\Controllers\ChildModeController;
use App\Http\Controllers\ChildQuizController;
use App\Http\Controllers\ChildVideoController;
use App\Http\Controllers\ChipPaymentController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\ParentPinController;
use App\Http\Controllers\PassPageController;
use App\Http\Controllers\PilotRequestController;
use App\Http\Controllers\PlusPageController;
use App\Http\Controllers\PlusWaitlistController;
use App\Http\Controllers\PreorderController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\PupilAccessController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReaderController;
use App\Http\Controllers\ReadingProgressController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\RewardPromiseController;
use App\Http\Controllers\SwitchAccountController;
use App\Http\Controllers\Teacher;
use App\Http\Controllers\VideoPageController;
use App\Http\Controllers\WeeklyReportController;
use App\Http\Middleware\AuthenticateOrPupil;
use App\Http\Middleware\EnsureCatalogOpen;
use App\Http\Middleware\EnsureInstitutionStaff;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');
Route::get('/bahasa/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');
Route::get('/tentang-kami', [LegalController::class, 'about'])->name('about');
// Installable app: the manifest (per language, with shortcuts) and how to install it.
Route::get('/manifest.webmanifest', ManifestController::class)->name('manifest');
Route::view('/pasang', 'install')->name('install');
// Phase 2: the animated videos for everyone, with free samples (hidden until the videos open).
Route::get('/video', VideoPageController::class)->name('videos.index');
Route::get('/privasi', [LegalController::class, 'privacy'])->name('privacy');
Route::get('/terma', [LegalController::class, 'terms'])->name('terms');
Route::get('/polisi-bayaran-balik', [LegalController::class, 'refund'])->name('refund');
Route::get('/polisi-penghantaran', [LegalController::class, 'delivery'])->name('delivery');

Route::get('/langganan', PassPageController::class)->name('pass.show');
// The yearly plan was called "Pas Tahunan" until 2026-10-25; old links keep working.
Route::permanentRedirect('/pas', '/langganan');

// Regular yearly pass once the early bird slots are gone (redirects to the pre-order before that).
Route::get('/beli/langganan', [PurchaseController::class, 'pass'])->name('purchase.pass');
Route::permanentRedirect('/beli/pas', '/beli/langganan');
Route::post('/beli/langganan', [PurchaseController::class, 'storePass'])
    ->middleware('throttle:10,1')
    ->name('purchase.pass.store');

// Phase 2: Langganan Plus (e-books and animated videos), on sale once the videos open.
Route::get('/langganan-plus', PlusPageController::class)->name('plus.show');
Route::post('/langganan-plus/beritahu', PlusWaitlistController::class)
    ->middleware('throttle:10,1')
    ->name('plus.waitlist');
Route::get('/beli/langganan-plus', [PurchaseController::class, 'plus'])->name('purchase.plus');
Route::post('/beli/langganan-plus', [PurchaseController::class, 'storePlus'])
    ->middleware('throttle:10,1')
    ->name('purchase.plus.store');

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

    // A signed-in account, or a pupil with a class code (Phase D).
    Route::middleware(AuthenticateOrPupil::class)->group(function () {
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
    Route::get('/tukar-akaun', [SwitchAccountController::class, 'show'])->name('switch-account');
    Route::post('/tukar-akaun', [SwitchAccountController::class, 'logout'])->name('switch-account.logout');
    Route::get('/pesanan/{order:reference}', [PreorderController::class, 'pay'])->name('preorder.pay');
    Route::post('/pesanan/{order:reference}/bayar', [ChipPaymentController::class, 'start'])
        ->middleware('throttle:10,1')
        ->name('payment.chip.start');
    Route::get('/pesanan/{order:reference}/selesai', [ChipPaymentController::class, 'back'])->name('payment.chip.back');

    Route::get('/pesanan/{order:reference}/resit', [ReceiptController::class, 'create'])->name('preorder.receipt');
    Route::post('/pesanan/{order:reference}/resit', [ReceiptController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('preorder.receipt.store');

    // Staff only (checked in the controller): watch a video before it is published.
    Route::get('/admin/video-pratonton/{video:slug}', Admin\VideoPreviewController::class)->name('admin.video.preview');

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
    Route::get('/akaun/langganan', [AccountController::class, 'billing'])->name('account.billing');
    Route::get('/akaun/profil', [AccountController::class, 'profile'])->name('account.profile');
    Route::get('/akaun/panduan', [AccountController::class, 'guide'])->name('account.guide');
    Route::get('/akaun/affiliate', [AccountController::class, 'affiliate'])->name('account.affiliate');
    Route::post('/akaun/affiliate', [AccountController::class, 'applyAffiliate'])->middleware('throttle:10,1')->name('account.affiliate.store');

    // Phase C: child profiles are only useful with the catalogue, so they open with it.
    Route::middleware(EnsureCatalogOpen::class)->prefix('/akaun/anak')->name('children.')->controller(ChildController::class)->group(function () {
        Route::get('/tambah', 'create')->name('create');
        Route::post('/', 'store')->middleware('throttle:20,1')->name('store');
        Route::get('/{child}/ubah', 'edit')->name('edit');
        Route::put('/{child}', 'update')->name('update');
        Route::delete('/{child}', 'destroy')->name('destroy');
    });

    Route::middleware(EnsureCatalogOpen::class)->group(function () {
        Route::get('/akaun/rak', [AccountController::class, 'shelf'])->name('account.shelf');
        Route::get('/akaun/anak', [AccountController::class, 'children'])->name('account.children');
        Route::get('/akaun/pencapaian', [AccountController::class, 'progress'])->name('account.progress');
        Route::post('/akaun/anak/{child}/janji', [RewardPromiseController::class, 'store'])->middleware('throttle:20,1')->name('reward-promises.store');
        Route::patch('/akaun/janji/{promise}/tunai', [RewardPromiseController::class, 'fulfil'])->name('reward-promises.fulfil');
        Route::delete('/akaun/janji/{promise}', [RewardPromiseController::class, 'destroy'])->name('reward-promises.destroy');
        Route::post('/akaun/anak/{child}/mod-anak', [ChildModeController::class, 'enter'])->name('children.child-mode');
        Route::put('/akaun/laporan-mingguan', [WeeklyReportController::class, 'update'])->name('weekly-report.update');
        Route::put('/akaun/pin', [ParentPinController::class, 'update'])->middleware('throttle:10,1')->name('parent-pin.update');
        Route::delete('/akaun/pin', [ParentPinController::class, 'destroy'])->middleware('throttle:10,1')->name('parent-pin.destroy');

        // The parent gate out of Mod Anak (pupils switch names instead, see PupilAccessController).
        Route::get('/mod-anak/keluar', [ChildModeController::class, 'gate'])->name('child-mode.gate');
        Route::post('/mod-anak/keluar', [ChildModeController::class, 'leave'])->middleware('throttle:10,1')->name('child-mode.leave');
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

    Route::controller(Teacher\ClassroomController::class)->prefix('/kelas')->name('classrooms.')->group(function () {
        Route::get('/baru', 'create')->name('create');
        Route::post('/', 'store')->middleware('throttle:20,1')->name('store');
        Route::get('/{classroom}', 'show')->name('show');
        Route::get('/{classroom}/ubah', 'edit')->name('edit');
        Route::put('/{classroom}', 'update')->name('update');
        Route::delete('/{classroom}', 'destroy')->name('destroy');
        Route::post('/{classroom}/kod-baharu', 'newCode')->middleware('throttle:10,1')->name('code');
    });
    Route::post('/kelas/{classroom}/murid', [Teacher\PupilController::class, 'store'])->middleware('throttle:20,1')->name('pupils.store');
    Route::delete('/kelas/{classroom}/murid/{pupil}', [Teacher\PupilController::class, 'destroy'])->name('pupils.destroy');

    Route::post('/guru', [Teacher\TeacherController::class, 'store'])->middleware('throttle:10,1')->name('teachers.store');
    Route::delete('/guru/{teacher}', [Teacher\TeacherController::class, 'destroy'])->name('teachers.destroy');
});

// Mod Anak, for a parent's child or a pupil (LockChildMode keeps them here).
Route::middleware([EnsureCatalogOpen::class, AuthenticateOrPupil::class])->group(function () {
    Route::prefix('/mod-anak')->name('child-mode.')->controller(ChildModeController::class)->group(function () {
        Route::get('/', 'home')->name('home');
        Route::get('/lencana', 'badges')->name('badges');
        Route::get('/lagu', 'song')->name('song');
    });
    Route::prefix('/mod-anak/kuiz/{book:slug}')->name('child-mode.quiz.')->controller(ChildQuizController::class)->group(function () {
        Route::get('/', 'show')->name('show');
        Route::post('/', 'submit')->middleware('throttle:20,1')->name('submit');
        Route::get('/keputusan', 'result')->name('result');
    });
    Route::post('/mod-anak/bacaan/{book:slug}', [ReadingProgressController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('child-mode.progress');

    // Games for subscribing families (2026-10-27).
    Route::get('/mod-anak/permainan/{game}', [ChildGameController::class, 'show'])->name('child-mode.game');
    Route::post('/mod-anak/permainan/{game}/masa', [ChildGameController::class, 'progress'])
        ->middleware('throttle:30,1')
        ->name('child-mode.game.progress');

    // Phase 2 animated videos.
    Route::get('/mod-anak/video/{video:slug}', [ChildVideoController::class, 'show'])->name('child-mode.video');
    Route::post('/mod-anak/video/{video:slug}/tonton', [ChildVideoController::class, 'progress'])
        ->middleware('throttle:20,1')
        ->name('child-mode.video.progress');
});

// Phase D: pupils join with a class code and pick their name.
Route::middleware(EnsureCatalogOpen::class)->prefix('/kelas')->name('pupil.')->controller(PupilAccessController::class)->group(function () {
    Route::get('/', 'join')->name('join');
    Route::post('/', 'enterCode')->middleware('throttle:10,1')->name('code');
    Route::get('/pilih', 'names')->name('names');
    Route::post('/pilih/{pupil}', 'choose')->middleware('throttle:30,1')->name('choose');
    Route::post('/keluar', 'leave')->name('leave');
    Route::post('/tutup', 'exit')->name('exit');
});
