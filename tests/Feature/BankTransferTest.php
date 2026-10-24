<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PreorderStatus;
use App\Enums\Role;
use App\Events\OrderPaid;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\Pass;
use App\Models\PaymentLog;
use App\Models\Preorder;
use App\Models\User;
use App\Services\BankTransfers;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => Storage::fake('local'));

function bankOrder(array $order = [], array $preorder = []): Order
{
    $created = Order::factory()->bankTransfer()->create($order);
    Preorder::factory()->create($preorder + [
        'user_id' => $created->user_id,
        'order_id' => $created->id,
        'reserved_until' => now()->addDay(),
    ]);

    return $created->fresh();
}

function receiptFile(string $name = 'resit.jpg', int $kb = 200): UploadedFile
{
    return UploadedFile::fake()->create($name, $kb, str_ends_with($name, '.pdf') ? 'application/pdf' : 'image/jpeg');
}

// --- Buyer uploads a receipt ------------------------------------------------

it('lets the buyer upload a receipt, which holds the slot until an admin decides', function () {
    $order = bankOrder();

    $this->actingAs($order->user)->get(route('preorder.receipt', $order))->assertOk()->assertSee('Muat naik resit');

    $this->actingAs($order->user)
        ->post(route('preorder.receipt.store', $order), ['receipt' => receiptFile()])
        ->assertRedirect(route('preorder.pay', $order));

    $order->refresh();

    expect($order->bank_receipt_path)->toStartWith('receipts/'.$order->reference)
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and($order->preorder->status)->toBe(PreorderStatus::AwaitingConfirmation)
        ->and(Preorder::query()->holdingSlot()->count())->toBe(1);

    Storage::disk('local')->assertExists($order->bank_receipt_path);

    $this->get(route('preorder.pay', $order))
        ->assertSee('Resit diterima')
        ->assertSee('Terima kasih, resit anda telah dihantar.');
});

it('keeps an awaiting receipt holding its slot even after the reservation time', function () {
    $order = bankOrder();
    app(BankTransfers::class)->uploadReceipt($order, receiptFile());

    $this->travel(2)->days();
    $this->artisan('asuh:expire-reservations');

    expect($order->preorder->fresh()->status)->toBe(PreorderStatus::AwaitingConfirmation)
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('replaces the previous receipt file when a new one is sent', function () {
    $order = bankOrder();
    app(BankTransfers::class)->uploadReceipt($order, receiptFile('pertama.jpg'));
    $first = $order->fresh()->bank_receipt_path;

    app(BankTransfers::class)->uploadReceipt($order->fresh(), receiptFile('kedua.pdf'));

    Storage::disk('local')->assertMissing($first);
    Storage::disk('local')->assertExists($order->fresh()->bank_receipt_path);
});

it('accepts a receipt for a lapsed reservation, since the money may already be sent', function () {
    $order = bankOrder([], ['reserved_until' => now()->subHour()]);
    $this->artisan('asuh:expire-reservations');

    $this->actingAs($order->user)
        ->get(route('preorder.pay', $order))
        ->assertSee('sudah membuat pindahan');

    $this->post(route('preorder.receipt.store', $order), ['receipt' => receiptFile()])->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($order->preorder->fresh()->status)->toBe(PreorderStatus::AwaitingConfirmation);
});

it('rejects files that are not images or PDFs, or are too large', function (UploadedFile $file) {
    $order = bankOrder();

    $this->actingAs($order->user)
        ->post(route('preorder.receipt.store', $order), ['receipt' => $file])
        ->assertSessionHasErrors('receipt');

    expect($order->fresh()->bank_receipt_path)->toBeNull();
})->with([
    'script' => fn () => UploadedFile::fake()->create('resit.php', 10, 'application/x-php'),
    'html' => fn () => UploadedFile::fake()->create('resit.html', 10, 'text/html'),
    'too large' => fn () => receiptFile('besar.jpg', 6000),
]);

it('stores receipts outside the public folder', function () {
    $order = bankOrder();
    app(BankTransfers::class)->uploadReceipt($order, receiptFile());

    expect(config('filesystems.disks.local.root'))->toBe(storage_path('app/private'))
        ->and(config('filesystems.disks.local.serve'))->toBeFalse();
});

it('does not let anyone else upload to or open the receipt page of an order', function () {
    $order = bankOrder();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('preorder.receipt', $order))->assertNotFound();
    $this->actingAs($stranger)->post(route('preorder.receipt.store', $order), ['receipt' => receiptFile()])->assertNotFound();
});

it('does not offer receipt upload for CHIP orders or paid orders', function () {
    $chip = Order::factory()->create(['payment_method' => PaymentMethod::Chip]);
    $paid = bankOrder(['status' => OrderStatus::Paid]);

    $this->actingAs($chip->user)->get(route('preorder.receipt', $chip))->assertRedirect(route('preorder.pay', $chip));
    $this->actingAs($paid->user)->get(route('preorder.receipt', $paid))->assertRedirect(route('preorder.pay', $paid));
});

// --- Admin confirms or rejects ---------------------------------------------

it('activates the pass when the admin confirms, recording who confirmed', function () {
    Event::fake([OrderPaid::class]);
    $admin = User::factory()->admin()->create();
    $order = bankOrder();
    app(BankTransfers::class)->uploadReceipt($order, receiptFile());

    app(BankTransfers::class)->confirm($order->fresh(), $admin);

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->confirmed_by)->toBe($admin->id)
        ->and($order->confirmed_at)->not->toBeNull()
        ->and($order->preorder->status)->toBe(PreorderStatus::Paid)
        ->and(Pass::query()->where('order_id', $order->id)->exists())->toBeTrue()
        ->and(PaymentLog::query()->where('order_id', $order->id)->pluck('event')->all())
        ->toBe(['receipt_uploaded', 'confirmed']);

    Event::assertDispatchedTimes(OrderPaid::class, 1);
});

it('confirms only once even if the admin clicks twice', function () {
    Event::fake([OrderPaid::class]);
    $admin = User::factory()->admin()->create();
    $order = bankOrder();

    app(BankTransfers::class)->confirm($order, $admin);
    app(BankTransfers::class)->confirm($order->fresh(), $admin);

    expect(Pass::query()->count())->toBe(1);
    Event::assertDispatchedTimes(OrderPaid::class, 1);
});

it('releases the slot when the admin rejects the transfer', function () {
    $admin = User::factory()->admin()->create();
    $order = bankOrder();
    app(BankTransfers::class)->uploadReceipt($order, receiptFile());

    app(BankTransfers::class)->reject($order->fresh(), $admin, 'Jumlah tidak sepadan');

    expect($order->fresh()->status)->toBe(OrderStatus::Failed)
        ->and($order->preorder->fresh()->status)->toBe(PreorderStatus::Expired)
        ->and(Preorder::query()->holdingSlot()->count())->toBe(0)
        ->and(PaymentLog::query()->where('event', 'rejected')->sole()->payload['reason'])->toBe('Jumlah tidak sepadan');
});

it('does not let an editor confirm or reject payments', function () {
    $editor = User::factory()->role(Role::Editor)->create();
    $order = bankOrder();

    expect(fn () => app(BankTransfers::class)->confirm($order, $editor))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(BankTransfers::class)->reject($order, $editor, 'x'))->toThrow(InvalidArgumentException::class)
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending);
});

// --- Admin panel -------------------------------------------------------------

it('shows orders to the admin with a filter for transfers awaiting confirmation', function () {
    $admin = User::factory()->admin()->create();
    $awaiting = bankOrder();
    app(BankTransfers::class)->uploadReceipt($awaiting, receiptFile());
    $other = Order::factory()->paid()->create();

    $this->actingAs($admin);

    Livewire::test(ListOrders::class)
        ->assertCanSeeTableRecords([$awaiting, $other])
        ->filterTable('awaiting_confirmation')
        ->assertCanSeeTableRecords([$awaiting])
        ->assertCanNotSeeTableRecords([$other]);
});

it('confirms a transfer from the admin order page', function () {
    $admin = User::factory()->admin()->create();
    $order = bankOrder();
    app(BankTransfers::class)->uploadReceipt($order, receiptFile());

    $this->actingAs($admin);

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->assertActionVisible('confirmPayment')
        ->callAction('confirmPayment')
        ->assertHasNoActionErrors();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('requires a reason to reject from the admin order page', function () {
    $admin = User::factory()->admin()->create();
    $order = bankOrder();

    $this->actingAs($admin);

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->callAction('rejectPayment', data: ['reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('hides the confirm action for CHIP orders', function () {
    $this->actingAs(User::factory()->admin()->create());
    $chip = Order::factory()->create();

    Livewire::test(ViewOrder::class, ['record' => $chip->getRouteKey()])
        ->assertActionHidden('confirmPayment')
        ->assertActionHidden('rejectPayment');
});

it('keeps editors out of the orders section', function () {
    $this->actingAs(User::factory()->role(Role::Editor)->create())
        ->get('/admin/pesanan')
        ->assertForbidden();
});

it('serves receipts only to admins', function () {
    $order = bankOrder();
    app(BankTransfers::class)->uploadReceipt($order, receiptFile());

    $this->get(route('admin.receipt', $order))->assertRedirect('/login');
    $this->actingAs($order->user)->get(route('admin.receipt', $order))->assertForbidden();
    $this->actingAs(User::factory()->role(Role::Editor)->create())->get(route('admin.receipt', $order))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.receipt', $order))->assertOk();
});
