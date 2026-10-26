<?php

use App\Enums\ChipMethod;
use App\Enums\OrderStatus;
use App\Mail\OrderReceipt;
use App\Models\Order;
use App\Models\Preorder;
use App\Models\Setting;
use App\Services\Chip\ProcessingFee;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.chip.brand_id' => 'brand-test',
        'services.chip.secret_key' => 'secret-test',
        'services.chip.test_mode' => true,
    ]);
    Setting::set('chip_fee_fpx_fixed', 1);
    Setting::set('chip_fee_fpx_percent', 0);
    Setting::set('chip_fee_ewallet_fixed', 0);
    Setting::set('chip_fee_ewallet_percent', 1.5);
    Cache::put('chip.payment_methods.brand-test', ['fpx', 'visa', 'mastercard', 'razer_tng', 'duitnow_qr'], now()->addHour());
});

function feeOrder(array $attributes = []): Order
{
    $order = Order::factory()->create($attributes + ['chip_purchase_id' => null]);
    Preorder::factory()->create(['user_id' => $order->user_id, 'order_id' => $order->id]);

    return $order->fresh();
}

it('works out the fee per method, never on cards', function () {
    $fees = app(ProcessingFee::class);

    expect($fees->for(ChipMethod::Fpx, 7900))->toBe(100)
        // 1.5% of RM79.00 = 118.5 sen, rounded up.
        ->and($fees->for(ChipMethod::Ewallet, 7900))->toBe(119)
        ->and($fees->for(ChipMethod::Card, 7900))->toBe(0);

    Setting::set('chip_fee_fpx_fixed', 0);
    expect($fees->for(ChipMethod::Fpx, 7900))->toBe(0);
});

it('lets the buyer pick a method on the pay page and shows each total', function () {
    $order = feeOrder();

    $this->actingAs($order->user)->get(route('preorder.pay', $order))
        ->assertOk()
        ->assertSee('Pilih kaedah bayaran')
        ->assertSee('FPX (perbankan dalam talian)')
        ->assertSee('Termasuk caj pemprosesan RM1.00')
        ->assertSee('RM80.00')
        ->assertSee('RM80.19')
        ->assertSee('Kad debit atau kredit')
        ->assertSee('Tiada caj pemprosesan');
});

it('hides methods the CHIP brand has not switched on', function () {
    Cache::put('chip.payment_methods.brand-test', ['fpx'], now()->addHour());
    $order = feeOrder();

    $this->actingAs($order->user)->get(route('preorder.pay', $order))
        ->assertSee('FPX (perbankan dalam talian)')
        ->assertDontSee('Kad debit atau kredit');

    $this->post(route('payment.chip.start', $order), ['kaedah' => 'card'])
        ->assertSessionHasErrors('payment');
});

it('adds the fee as its own line and only allows the chosen method on CHIP', function () {
    $order = feeOrder();
    Http::fake(['gate.chip-in.asia/api/v1/purchases*' => Http::response(
        ['id' => 'pur_fee', 'checkout_url' => 'https://gate.chip-in.asia/p/pur_fee/', 'status' => 'created'], 201,
    )]);

    $this->actingAs($order->user)
        ->post(route('payment.chip.start', $order), ['kaedah' => 'fpx'])
        ->assertRedirect('https://gate.chip-in.asia/p/pur_fee/');

    expect($order->fresh())
        ->chip_method->toBe(ChipMethod::Fpx)
        ->processing_fee_sen->toBe(100)
        ->totalSen()->toBe(8000);

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request['purchase']['products'][0]['price'] === 7900
        && $request['purchase']['products'][1]['price'] === 100
        && $request['payment_method_whitelist'] === ['fpx']);
});

it('charges no fee for cards', function () {
    $order = feeOrder();
    Http::fake(['gate.chip-in.asia/api/v1/purchases*' => Http::response(
        ['id' => 'pur_card', 'checkout_url' => 'https://gate.chip-in.asia/p/pur_card/', 'status' => 'created'], 201,
    )]);

    $this->actingAs($order->user)->post(route('payment.chip.start', $order), ['kaedah' => 'card']);

    expect($order->fresh()->processing_fee_sen)->toBe(0);
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && count($request['purchase']['products']) === 1
        && $request['payment_method_whitelist'] === ['visa', 'mastercard']);
});

it('cancels the old checkout before making one for another method', function () {
    $order = feeOrder(['chip_purchase_id' => 'pur_old', 'chip_method' => 'card']);
    Http::fake([
        'gate.chip-in.asia/api/v1/purchases/pur_old/cancel/' => Http::response(['status' => 'cancelled']),
        'gate.chip-in.asia/api/v1/purchases/pur_old/' => Http::response(['id' => 'pur_old', 'status' => 'viewed', 'checkout_url' => 'https://gate.chip-in.asia/p/pur_old/']),
        'gate.chip-in.asia/api/v1/purchases/' => Http::response(['id' => 'pur_fpx', 'checkout_url' => 'https://gate.chip-in.asia/p/pur_fpx/', 'status' => 'created'], 201),
    ]);

    $this->actingAs($order->user)
        ->post(route('payment.chip.start', $order), ['kaedah' => 'fpx'])
        ->assertRedirect('https://gate.chip-in.asia/p/pur_fpx/');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), 'purchases/pur_old/cancel/'));
    expect($order->fresh()->chip_purchase_id)->toBe('pur_fpx');
});

it('keeps the old checkout when CHIP will not cancel it', function () {
    $order = feeOrder(['chip_purchase_id' => 'pur_old', 'chip_method' => 'card']);
    Http::fake([
        'gate.chip-in.asia/api/v1/purchases/pur_old/cancel/' => Http::response(['error' => 'no'], 400),
        'gate.chip-in.asia/api/v1/purchases/pur_old/' => Http::response(['id' => 'pur_old', 'status' => 'viewed', 'checkout_url' => 'https://gate.chip-in.asia/p/pur_old/']),
    ]);

    $this->actingAs($order->user)
        ->post(route('payment.chip.start', $order), ['kaedah' => 'fpx'])
        ->assertSessionHasErrors('payment');

    expect($order->fresh())->chip_purchase_id->toBe('pur_old')->chip_method->toBe(ChipMethod::Card);
});

it('activates only when CHIP confirms the price plus the fee', function () {
    $privateKey = file_get_contents(base_path('tests/Fixtures/chip/test-private.pem'));
    Cache::put('chip.public_key', file_get_contents(base_path('tests/Fixtures/chip/test-public.pem')), now()->addDay());
    $order = feeOrder(['chip_purchase_id' => 'pur_fee', 'chip_method' => 'fpx', 'processing_fee_sen' => 100]);

    $callback = function (int $total) use ($order, $privateKey) {
        $payload = ['id' => 'pur_fee', 'status' => 'paid', 'is_test' => true, 'reference' => $order->reference,
            'purchase' => ['total' => $total, 'currency' => 'MYR']];
        Http::fake(['gate.chip-in.asia/api/v1/purchases/pur_fee/*' => Http::response($payload)]);
        $body = json_encode($payload);
        openssl_sign($body, $raw, $privateKey, OPENSSL_ALGO_SHA256);

        return $this->call('POST', '/webhooks/chip', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => base64_encode($raw)], $body);
    };

    $callback(7900);
    expect($order->fresh()->status)->toBe(OrderStatus::Pending);

    Http::swap(new Factory);
    $callback(8000);
    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('prints the price, the fee and the total on the e-mail receipt', function () {
    $order = feeOrder(['chip_method' => 'fpx', 'processing_fee_sen' => 100, 'status' => OrderStatus::Paid, 'paid_at' => now()]);

    (new OrderReceipt($order))->assertSeeInHtml('Caj pemprosesan')
        ->assertSeeInHtml('RM1.00')
        ->assertSeeInHtml('RM80.00')
        ->assertSeeInHtml('FPX (perbankan dalam talian)');
});
