<?php

use App\Enums\PreorderStatus;
use App\Models\Order;
use App\Models\Pass;
use App\Models\Plan;
use App\Models\Preorder;
use Database\Seeders\PlanSeeder;
use Illuminate\Database\QueryException;

it('seeds the pass plans with prices in sen', function () {
    $this->seed(PlanSeeder::class);

    expect(Plan::findByCode(Plan::PAS_TAHUNAN)->price_sen)->toBe(9900)
        ->and(Plan::findByCode(Plan::PAS_TAHUNAN_EARLY)->price_sen)->toBe(7900)
        // Langganan Plus is active but unpriced: not on sale until the admin sets a price.
        ->and(Plan::findByCode(Plan::PAS_PLUS)->price_sen)->toBe(0);
});

it('does not overwrite a price the admin has changed when re-seeded', function () {
    $this->seed(PlanSeeder::class);
    Plan::findByCode(Plan::PAS_TAHUNAN_EARLY)->update(['price_sen' => 6900]);

    $this->seed(PlanSeeder::class);

    expect(Plan::findByCode(Plan::PAS_TAHUNAN_EARLY)->price_sen)->toBe(6900)
        ->and(Plan::query()->count())->toBe(5);
});

it('gives every order a unique reference', function () {
    $orders = Order::factory()->count(5)->create();

    expect($orders->pluck('reference')->unique())->toHaveCount(5)
        ->and($orders->first()->reference)->toStartWith('ASUH-');
});

it('allows only one pass per order', function () {
    $order = Order::factory()->paid()->create();
    $pass = [
        'user_id' => $order->user_id,
        'plan_id' => $order->plan_id,
        'order_id' => $order->id,
        'starts_at' => now(),
        'ends_at' => now()->addYear(),
    ];

    Pass::query()->create($pass);
    Pass::query()->create($pass);
})->throws(QueryException::class);

it('counts only pre-orders that are holding a slot', function () {
    Preorder::factory()->create(); // active reservation
    Preorder::factory()->status(PreorderStatus::AwaitingConfirmation)->create();
    Preorder::factory()->status(PreorderStatus::Paid)->create();
    Preorder::factory()->expiredReservation()->create();
    Preorder::factory()->status(PreorderStatus::Expired)->create();
    Preorder::factory()->status(PreorderStatus::Refunded)->create();

    expect(Preorder::query()->holdingSlot()->count())->toBe(3);
});

it('reports whether a pass is active', function () {
    $order = Order::factory()->paid()->create();

    $pass = Pass::query()->create([
        'user_id' => $order->user_id,
        'plan_id' => $order->plan_id,
        'order_id' => $order->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addYear(),
    ]);

    expect($pass->isActive())->toBeTrue()
        ->and($order->user->activePass()?->is($pass))->toBeTrue();

    $pass->update(['ends_at' => now()->subMinute()]);

    expect($pass->fresh()->isActive())->toBeFalse()
        ->and($order->user->activePass())->toBeNull();
});
