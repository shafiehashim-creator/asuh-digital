<?php

use App\Enums\OrderStatus;
use App\Enums\PreorderStatus;
use App\Models\Order;
use App\Models\Preorder;

function preorderWithOrder(array $preorder = [], array $order = []): Preorder
{
    $created = Order::factory()->create($order);

    return Preorder::factory()->create(['user_id' => $created->user_id, 'order_id' => $created->id] + $preorder);
}

it('expires lapsed reservations and fails their orders', function () {
    $lapsed = preorderWithOrder(['reserved_until' => now()->subMinute()]);

    $this->artisan('asuh:expire-reservations')->assertSuccessful();

    expect($lapsed->fresh()->status)->toBe(PreorderStatus::Expired)
        ->and($lapsed->order->fresh()->status)->toBe(OrderStatus::Failed);
});

it('leaves live, paid and awaiting reservations alone', function () {
    $live = preorderWithOrder(['reserved_until' => now()->addMinutes(10)]);
    $paid = preorderWithOrder(['status' => PreorderStatus::Paid, 'reserved_until' => now()->subDay()], ['status' => OrderStatus::Paid]);
    $awaiting = preorderWithOrder(['status' => PreorderStatus::AwaitingConfirmation, 'reserved_until' => now()->subDay()]);

    $this->artisan('asuh:expire-reservations')->assertSuccessful();

    expect($live->fresh()->status)->toBe(PreorderStatus::Reserved)
        ->and($paid->fresh()->status)->toBe(PreorderStatus::Paid)
        ->and($paid->order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($awaiting->fresh()->status)->toBe(PreorderStatus::AwaitingConfirmation)
        ->and($awaiting->order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('is scheduled every minute', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('asuh:expire-reservations');
});

it('tells the buyer when their reservation has expired', function () {
    $lapsed = preorderWithOrder(['reserved_until' => now()->subMinute()]);
    $this->artisan('asuh:expire-reservations');

    $this->actingAs($lapsed->user)
        ->get(route('preorder.pay', $lapsed->order))
        ->assertSee('Tempahan telah tamat tempoh')
        ->assertSee('Tempah semula');
});
