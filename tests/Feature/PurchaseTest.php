<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PreorderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Mail\OrderReceipt;
use App\Models\Book;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\Pass;
use App\Models\Plan;
use App\Models\Preorder;
use App\Models\Setting;
use App\Models\User;
use App\Services\BankTransfers;
use App\Services\PaymentProcessor;
use Database\Seeders\PlanSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    Notification::fake();
    Mail::fake();
});

function sellOutEarlyBird(): void
{
    Setting::set('preorder_slot_limit', 1);
    Preorder::factory()->status(PreorderStatus::Paid)->create();
}

function guestBuyer(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ibu Pembeli',
        'email' => 'pembeli@example.com',
        'phone' => '012 345 6789',
        'password' => 'rahsia123',
        'password_confirmation' => 'rahsia123',
        'payment_method' => 'chip',
        'pdpa_consent' => '1',
    ], $overrides);
}

// --- Regular yearly pass -------------------------------------------------------

it('sends buyers to the early bird pre-order while slots remain', function () {
    $this->get(route('purchase.pass'))->assertRedirect(route('preorder.create'));
    $this->post(route('purchase.pass.store'), guestBuyer())->assertRedirect(route('preorder.create'));

    expect(Order::query()->count())->toBe(0);
});

it('sells the regular pass at full price once the early bird is sold out', function () {
    sellOutEarlyBird();

    $this->get(route('purchase.pass'))->assertOk()->assertSee('Beli Langganan')->assertSee('RM99')->assertSee('Paling berbaloi');

    $this->post(route('purchase.pass.store'), guestBuyer())->assertRedirect();

    $order = Order::query()->latest('id')->first();
    $user = User::query()->where('email', 'pembeli@example.com')->sole();

    expect($order->user_id)->toBe($user->id)
        ->and($order->plan->code)->toBe(Plan::PAS_TAHUNAN)
        ->and($order->amount_sen)->toBe(9900)
        ->and($order->preorder)->toBeNull()
        ->and($order->status)->toBe(OrderStatus::Pending);
    $this->assertAuthenticatedAs($user);
});

it('follows the regular price set by the admin', function () {
    sellOutEarlyBird();
    Plan::findByCode(Plan::PAS_TAHUNAN)->update(['price_sen' => 10900]);

    $this->post(route('purchase.pass.store'), guestBuyer());

    expect(Order::query()->latest('id')->first()->amount_sen)->toBe(10900);
});

it('requires consent and valid account details from guests', function () {
    sellOutEarlyBird();

    $this->post(route('purchase.pass.store'), [])->assertSessionHasErrors(['name', 'email', 'phone', 'password', 'payment_method', 'pdpa_consent']);
});

it('does not create duplicate orders when a buyer comes back or switches method', function () {
    sellOutEarlyBird();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('purchase.pass.store'), ['payment_method' => 'chip']);
    $this->actingAs($user)->post(route('purchase.pass.store'), ['payment_method' => 'bank_transfer']);

    $orders = $user->orders()->whereDoesntHave('preorder')->get();
    expect($orders)->toHaveCount(1)
        ->and($orders->first()->payment_method)->toBe(PaymentMethod::BankTransfer);
});

it('starts a renewed pass when the current one ends', function () {
    sellOutEarlyBird();
    $user = User::factory()->create();
    $current = Order::factory()->paid()->create(['user_id' => $user->id]);
    $currentPass = Pass::query()->create([
        'user_id' => $user->id, 'plan_id' => $current->plan_id, 'order_id' => $current->id,
        'starts_at' => now()->subMonths(11), 'ends_at' => now()->addMonth(),
    ]);

    $this->actingAs($user)->get(route('purchase.pass'))->assertSee('Langganan baharu bermula sebaik langganan semasa tamat');
    $this->actingAs($user)->post(route('purchase.pass.store'), ['payment_method' => 'chip']);

    $renewal = $user->orders()->latest('id')->first();
    app(PaymentProcessor::class)->markPaid($renewal);

    expect($renewal->pass->starts_at->equalTo($currentPass->ends_at))->toBeTrue()
        ->and($renewal->pass->ends_at->toDateString())->toBe($currentPass->ends_at->copy()->addMonths(12)->toDateString());
});

it('shows a regular pass order without any slot wording on the pay page', function () {
    sellOutEarlyBird();
    $this->post(route('purchase.pass.store'), guestBuyer(['payment_method' => 'bank_transfer']));
    $order = Order::query()->latest('id')->first();

    $this->get(route('preorder.pay', $order))
        ->assertOk()
        ->assertSee('Langganan Tahunan')
        ->assertDontSee('Slot anda ditempah')
        ->assertSee('muat naik resit');
});

it('lets the admin confirm a regular pass bank transfer and finds it in the awaiting filter', function () {
    Storage::fake('local');
    sellOutEarlyBird();
    $this->post(route('purchase.pass.store'), guestBuyer(['payment_method' => 'bank_transfer']));
    $order = Order::query()->latest('id')->first();

    app(BankTransfers::class)->uploadReceipt($order, UploadedFile::fake()->create('resit.jpg', 50, 'image/jpeg'));

    expect($order->fresh()->isAwaitingConfirmation())->toBeTrue()
        ->and(OrderResource::awaitingConfirmationQuery(Order::query())->pluck('id')->all())->toBe([$order->id]);

    app(BankTransfers::class)->confirm($order->fresh(), User::factory()->admin()->create());

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->user->activePass())->not->toBeNull();
});

// --- Single e-books ------------------------------------------------------------

it('offers a single book purchase only for priced, published books', function () {
    Setting::set('catalog_open', true);
    $priced = Book::factory()->published()->create(['single_price_sen' => 990]);
    $free = Book::factory()->published()->create(['single_price_sen' => null]);

    $this->get(route('catalog.show', $priced))->assertSee('Beli buku ini RM9.90');
    $this->get(route('catalog.show', $free))->assertDontSee('Beli buku ini');
    $this->get(route('purchase.book', $free))->assertNotFound();
});

it('sells a single book and opens it for good once paid', function () {
    Setting::set('catalog_open', true);
    $book = Book::factory()->published()->withFile()->create(['single_price_sen' => 990]);

    $this->get(route('purchase.book', $book))->assertOk()->assertSee($book->title)->assertSee('RM9.90');
    $this->post(route('purchase.book.store', $book), guestBuyer())->assertRedirect();

    $order = Order::query()->latest('id')->first();
    expect($order->book_id)->toBe($book->id)
        ->and($order->plan_id)->toBeNull()
        ->and($order->amount_sen)->toBe(990)
        ->and($order->itemName())->toBe('E-book: '.$book->title);

    $buyer = $order->user;
    expect($buyer->canReadBook($book))->toBeFalse();

    app(PaymentProcessor::class)->markPaid($order);

    expect(Entitlement::query()->where('user_id', $buyer->id)->where('book_id', $book->id)->exists())->toBeTrue()
        ->and($buyer->fresh()->canReadBook($book))->toBeTrue()
        ->and(Pass::query()->where('user_id', $buyer->id)->exists())->toBeFalse();

    Mail::assertQueued(OrderReceipt::class);
});

it('does not sell a book to someone who can already read it', function () {
    Setting::set('catalog_open', true);
    $book = Book::factory()->published()->create(['single_price_sen' => 990]);
    $user = User::factory()->create();
    Entitlement::query()->create(['user_id' => $user->id, 'book_id' => $book->id]);

    $this->actingAs($user)->get(route('purchase.book', $book))->assertRedirect(route('catalog.show', $book));
    $this->actingAs($user)->post(route('purchase.book.store', $book), ['payment_method' => 'chip'])->assertSessionHasErrors('purchase');
});

it('keeps single book purchases hidden while the catalogue is closed', function () {
    $book = Book::factory()->published()->create(['single_price_sen' => 990]);

    $this->get(route('purchase.book', $book))->assertNotFound();
});
