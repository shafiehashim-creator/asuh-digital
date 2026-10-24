<?php

use App\Enums\Role;
use App\Models\Order;
use App\Models\Pass;
use App\Models\PaymentLog;
use App\Models\PilotRequest;
use App\Models\Plan;
use App\Models\Preorder;
use App\Models\Setting;
use App\Models\User;
use App\Services\BankTransfers;
use App\Services\PaymentProcessor;
use Database\Seeders\PlanSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Mail::fake();
    Storage::fake('local');
    config(['services.chip.test_mode' => true]);
    $this->seed(PlanSeeder::class);

    // A realistic test-era database.
    $this->admin = User::factory()->admin()->create();
    $this->editor = User::factory()->role(Role::Editor)->create();

    $paid = Order::factory()->create();
    Preorder::factory()->create(['user_id' => $paid->user_id, 'order_id' => $paid->id]);
    app(PaymentProcessor::class)->markPaid($paid);

    $bank = Order::factory()->bankTransfer()->create();
    Preorder::factory()->create(['user_id' => $bank->user_id, 'order_id' => $bank->id]);
    app(BankTransfers::class)->uploadReceipt($bank, UploadedFile::fake()->create('resit.jpg', 50, 'image/jpeg'));
    $this->receiptPath = $bank->fresh()->bank_receipt_path;

    PilotRequest::factory()->count(2)->create();
    Setting::set('company_name', 'Syarikat Kekal Sdn Bhd');
    Plan::findByCode(Plan::PAS_TAHUNAN_EARLY)->update(['price_sen' => 6900]);
});

it('only shows what would be deleted without --confirm', function () {
    $this->artisan('asuh:purge-test-data')
        ->expectsOutputToContain('Mod tinjauan sahaja')
        ->assertSuccessful();

    expect(Order::query()->count())->toBe(2)
        ->and(User::query()->where('role', 'parent')->count())->toBe(2);
});

it('deletes nothing unless the confirmation phrase is typed exactly', function (string $typed) {
    $this->artisan('asuh:purge-test-data --confirm')
        ->expectsQuestion('Taip KOSONGKAN untuk memadam data di atas secara kekal', $typed)
        ->assertFailed();

    expect(Order::query()->count())->toBe(2);
    Storage::disk('local')->assertExists($this->receiptPath);
})->with(['salah' => ['kosongkan'], 'kosong' => ['']]);

it('clears test orders, passes, pre-orders, logs, parents and receipt files', function () {
    $this->artisan('asuh:purge-test-data --confirm')
        ->expectsQuestion('Taip KOSONGKAN untuk memadam data di atas secara kekal', 'KOSONGKAN')
        ->assertSuccessful();

    expect(Order::query()->count())->toBe(0)
        ->and(Pass::query()->count())->toBe(0)
        ->and(Preorder::query()->count())->toBe(0)
        ->and(PaymentLog::query()->count())->toBe(0)
        ->and(User::query()->where('role', 'parent')->count())->toBe(0);

    Storage::disk('local')->assertMissing($this->receiptPath);
});

it('keeps admins, editors, settings, prices and pilot requests', function () {
    $this->artisan('asuh:purge-test-data --confirm')
        ->expectsQuestion('Taip KOSONGKAN untuk memadam data di atas secara kekal', 'KOSONGKAN');

    expect($this->admin->fresh())->not->toBeNull()
        ->and($this->editor->fresh())->not->toBeNull()
        ->and(Setting::get('company_name'))->toBe('Syarikat Kekal Sdn Bhd')
        ->and(Plan::findByCode(Plan::PAS_TAHUNAN_EARLY)->price_sen)->toBe(6900)
        ->and(PilotRequest::query()->count())->toBe(2);
});

it('deletes pilot requests only when asked', function () {
    $this->artisan('asuh:purge-test-data --confirm --include-pilots')
        ->expectsQuestion('Taip KOSONGKAN untuk memadam data di atas secara kekal', 'KOSONGKAN');

    expect(PilotRequest::query()->count())->toBe(0);
});

it('frees every pre-order slot again', function () {
    $this->artisan('asuh:purge-test-data --confirm')
        ->expectsQuestion('Taip KOSONGKAN untuk memadam data di atas secara kekal', 'KOSONGKAN');

    expect(app(App\Services\PreorderSlots::class)->remaining())->toBe(100);
});

it('refuses to run once CHIP is in live mode', function () {
    config(['services.chip.test_mode' => false]);

    $this->artisan('asuh:purge-test-data --confirm')
        ->expectsOutputToContain('mod LIVE')
        ->assertFailed();

    expect(Order::query()->count())->toBe(2)
        ->and(User::query()->where('role', 'parent')->count())->toBe(2);
});
