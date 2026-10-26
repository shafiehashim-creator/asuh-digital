<?php

use App\Enums\OrderStatus;
use App\Enums\PreorderStatus;
use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\Pass;
use App\Models\PaymentLog;
use App\Models\Preorder;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.chip.brand_id' => 'brand-test',
        'services.chip.secret_key' => 'secret-test',
        'services.chip.test_mode' => true,
        'services.chip.webhook_public_key' => null,
    ]);

    // A real (test-only) key pair, so signatures are verified exactly as in production.
    $this->privateKey = file_get_contents(base_path('tests/Fixtures/chip/test-private.pem'));
    Cache::put('chip.public_key', file_get_contents(base_path('tests/Fixtures/chip/test-public.pem')), now()->addDay());
});

function chipOrder(array $order = [], array $preorder = []): Order
{
    $created = Order::factory()->create($order + ['chip_purchase_id' => 'pur_123']);
    Preorder::factory()->create(['user_id' => $created->user_id, 'order_id' => $created->id] + $preorder);

    return $created->fresh();
}

function chipPurchase(Order $order, array $overrides = []): array
{
    return array_replace_recursive([
        'id' => $order->chip_purchase_id,
        'event_type' => 'purchase.paid',
        'status' => 'paid',
        'reference' => $order->reference,
        'is_test' => true,
        'purchase' => ['total' => $order->amount_sen, 'currency' => 'MYR'],
        'checkout_url' => 'https://gate.chip-in.asia/p/pur_123/',
    ], $overrides);
}

function signedCallback($test, array $payload, ?string $signature = null)
{
    $body = json_encode($payload);

    if ($signature === null) {
        openssl_sign($body, $raw, $test->privateKey, OPENSSL_ALGO_SHA256);
        $signature = base64_encode($raw);
    }

    return $test->call('POST', '/webhooks/chip', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE' => $signature,
    ], $body);
}

function fakeChipLookup(array $purchase): void
{
    // Keyed by purchase id: Http::fake stubs accumulate and the first match wins.
    Http::fake(["gate.chip-in.asia/api/v1/purchases/{$purchase['id']}/*" => Http::response($purchase)]);
}

// --- Starting a payment -----------------------------------------------------

it('creates a CHIP purchase and sends the buyer to checkout', function () {
    $order = chipOrder(['chip_purchase_id' => null]);
    Http::fake(['gate.chip-in.asia/api/v1/purchases*' => Http::response(
        ['id' => 'pur_new', 'checkout_url' => 'https://gate.chip-in.asia/p/pur_new/', 'status' => 'created'], 201,
    )]);

    $this->actingAs($order->user)
        ->post(route('payment.chip.start', $order), ['kaedah' => 'fpx'])
        ->assertRedirect('https://gate.chip-in.asia/p/pur_new/');

    expect($order->fresh()->chip_purchase_id)->toBe('pur_new');

    Http::assertSent(function ($request) use ($order) {
        return $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer secret-test')
            && $request['brand_id'] === 'brand-test'
            && $request['reference'] === $order->reference
            && $request['purchase']['products'][0]['price'] === 7900
            && $request['client']['email'] === $order->user->email;
    });
});

it('reuses an unpaid purchase instead of creating a second one', function () {
    $order = chipOrder(['chip_method' => 'fpx']);
    fakeChipLookup(chipPurchase($order, ['status' => 'viewed']));

    $this->actingAs($order->user)
        ->post(route('payment.chip.start', $order), ['kaedah' => 'fpx'])
        ->assertRedirect('https://gate.chip-in.asia/p/pur_123/');

    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
});

it('will not start a payment for an expired reservation or a paid order', function (array $order, array $preorder) {
    $created = chipOrder($order, $preorder);
    Http::fake();

    $this->actingAs($created->user)
        ->post(route('payment.chip.start', $created), ['kaedah' => 'fpx'])
        ->assertRedirect(route('preorder.pay', $created));

    Http::assertNothingSent();
})->with([
    'expired' => [[], ['reserved_until' => now()->subMinute()]],
    'paid' => [['status' => OrderStatus::Paid], []],
]);

it('only lets the owner start the payment', function () {
    $order = chipOrder();
    $this->actingAs(User::factory()->create())
        ->post(route('payment.chip.start', $order))
        ->assertNotFound();
});

it('shows a friendly error when CHIP cannot be reached', function () {
    $order = chipOrder(['chip_purchase_id' => null]);
    Http::fake(['*' => Http::response(['error' => 'down'], 500)]);

    $this->actingAs($order->user)
        ->post(route('payment.chip.start', $order), ['kaedah' => 'fpx'])
        ->assertRedirect(route('preorder.pay', $order))
        ->assertSessionHasErrors('payment');
});

it('never activates the pass from the return redirect', function () {
    $order = chipOrder();

    $this->actingAs($order->user)
        ->get(route('payment.chip.back', $order))
        ->assertRedirect(route('preorder.pay', $order));

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and(Pass::query()->count())->toBe(0);

    $this->get(route('preorder.pay', $order))->assertSee('sedang mengesahkan bayaran');
});

// --- Callback ---------------------------------------------------------------

it('activates the pass from a signed, confirmed callback', function () {
    Event::fake([OrderPaid::class]);
    $order = chipOrder();
    fakeChipLookup(chipPurchase($order));

    signedCallback($this, chipPurchase($order))->assertOk();

    $order->refresh();
    $pass = Pass::query()->sole();

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->preorder->status)->toBe(PreorderStatus::Paid)
        ->and($pass->user_id)->toBe($order->user_id)
        ->and($pass->isActive())->toBeTrue()
        ->and($pass->ends_at->diffInDays(now()->addMonths(12)))->toBeLessThan(1);

    Event::assertDispatchedTimes(OrderPaid::class, 1);
    expect(PaymentLog::query()->sole())
        ->provider->toBe('chip')
        ->signature_valid->toBeTrue()
        ->order_id->toBe($order->id);
});

it('activates the pass only once when CHIP repeats the callback', function () {
    Event::fake([OrderPaid::class]);
    $order = chipOrder();
    fakeChipLookup(chipPurchase($order));

    signedCallback($this, chipPurchase($order))->assertOk();
    signedCallback($this, chipPurchase($order))->assertOk();
    signedCallback($this, chipPurchase($order))->assertOk();

    expect(Pass::query()->count())->toBe(1)
        ->and(PaymentLog::query()->count())->toBe(3);
    Event::assertDispatchedTimes(OrderPaid::class, 1);
});

it('rejects a callback with a bad or missing signature and logs it', function (?string $signature) {
    $order = chipOrder();
    Http::fake();

    signedCallback($this, chipPurchase($order), $signature)->assertStatus(400);

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and(PaymentLog::query()->sole()->signature_valid)->toBeFalse();
    Http::assertNothingSent();
})->with([
    'forged' => [base64_encode('not-a-real-signature')],
    'missing' => [''],
]);

it('rejects a callback whose body was changed after signing', function () {
    $order = chipOrder();
    $payload = chipPurchase($order);
    openssl_sign(json_encode($payload), $raw, $this->privateKey, OPENSSL_ALGO_SHA256);

    $payload['purchase']['total'] = 100; // tampered

    signedCallback($this, $payload, base64_encode($raw))->assertStatus(400);
    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('does not trust the callback body when CHIP says the purchase is not paid', function () {
    $order = chipOrder();
    fakeChipLookup(chipPurchase($order, ['status' => 'created']));

    signedCallback($this, chipPurchase($order))->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('refuses a payment for the wrong amount', function () {
    $order = chipOrder();
    fakeChipLookup(chipPurchase($order, ['purchase' => ['total' => 100]]));

    signedCallback($this, chipPurchase($order))->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and(Pass::query()->count())->toBe(0);
});

it('never lets a test payment activate a pass on the live site', function () {
    config(['services.chip.test_mode' => false]);
    $order = chipOrder();
    fakeChipLookup(chipPurchase($order, ['is_test' => true]));

    signedCallback($this, chipPurchase($order))->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('ignores callbacks for unknown purchases', function () {
    Http::fake();
    $order = chipOrder();

    signedCallback($this, chipPurchase($order, ['id' => 'pur_someone_else']))->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
    Http::assertNothingSent();
});

it('honours a payment that arrives after the reservation expired', function () {
    $order = chipOrder([], ['reserved_until' => now()->subHour()]);
    $this->artisan('asuh:expire-reservations');
    expect($order->fresh()->status)->toBe(OrderStatus::Failed);

    fakeChipLookup(chipPurchase($order));
    signedCallback($this, chipPurchase($order))->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->preorder->fresh()->status)->toBe(PreorderStatus::Paid)
        ->and(Pass::query()->count())->toBe(1);
});

it('waits for the launch date when the admin chose that start rule', function () {
    Setting::set('pass_start_rule', 'on_launch');
    $order = chipOrder();
    fakeChipLookup(chipPurchase($order));

    signedCallback($this, chipPurchase($order))->assertOk();

    expect(Pass::query()->sole())
        ->starts_at->toBeNull()
        ->ends_at->toBeNull();

    Setting::set('launch_date', '2027-03-01');
    $second = chipOrder(['chip_purchase_id' => 'pur_456']);
    fakeChipLookup(chipPurchase($second));
    signedCallback($this, chipPurchase($second))->assertOk();

    expect($second->pass->starts_at->toDateString())->toBe('2027-03-01')
        ->and($second->pass->ends_at->toDateString())->toBe('2028-03-01');
});

it('does not need a CSRF token for the CHIP callback', function () {
    $order = chipOrder();
    fakeChipLookup(chipPurchase($order));

    $body = json_encode(chipPurchase($order));
    openssl_sign($body, $raw, $this->privateKey, OPENSSL_ALGO_SHA256);

    // Real middleware stack, no test helpers that skip CSRF.
    $this->withMiddleware()
        ->call('POST', '/webhooks/chip', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => base64_encode($raw)], $body)
        ->assertOk();
});
