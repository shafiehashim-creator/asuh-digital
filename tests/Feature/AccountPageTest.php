<?php

use App\Actions\Fortify\CreateNewUser;
use App\Enums\Role;
use App\Models\Order;
use App\Models\Pass;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('sends guests to the login page', function () {
    $this->get('/akaun')->assertRedirect('/login');
});

it('shows an empty account with a call to get the pass', function () {
    $this->actingAs(User::factory()->create(['name' => 'Aminah']))
        ->get('/akaun')
        ->assertOk()
        ->assertSee('Assalamualaikum, Aminah')
        ->assertSee('Anda belum mempunyai Langganan Tahunan yang aktif.')
        ->assertSee('Tiada pesanan lagi.');
});

it('shows an active pass and the order', function () {
    $order = Order::factory()->paid()->create();
    Pass::query()->create([
        'user_id' => $order->user_id,
        'plan_id' => $order->plan_id,
        'order_id' => $order->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->setDate(2027, 10, 24),
    ]);

    $this->actingAs($order->user)
        ->get('/akaun')
        ->assertOk()
        ->assertSee('Aktif')
        ->assertSee('24 Oktober 2027')
        ->assertSee($order->reference)
        ->assertSee('RM79.00')
        ->assertSee('Dibayar');
});

it('explains a paid pass that waits for the launch date', function () {
    $order = Order::factory()->paid()->create();
    Pass::query()->create([
        'user_id' => $order->user_id,
        'plan_id' => $order->plan_id,
        'order_id' => $order->id,
    ]);

    $this->actingAs($order->user)
        ->get('/akaun')
        ->assertSee('bermula pada tarikh platform ASUH Digital dilancarkan');
});

it('only shows the signed-in parent their own orders', function () {
    $mine = Order::factory()->create();
    $theirs = Order::factory()->create();

    $this->actingAs($mine->user)
        ->get('/akaun')
        ->assertSee($mine->reference)
        ->assertDontSee($theirs->reference);
});

it('updates the profile and normalises the phone number', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/user/profile-information', [
            'name' => 'Nama Baharu',
            'email' => $user->email,
            'phone' => '+60 12-345 6789',
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh())
        ->name->toBe('Nama Baharu')
        ->phone->toBe('0123456789');
});

it('rejects an invalid phone number', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/user/profile-information', [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '12345',
        ])
        ->assertSessionHasErrorsIn('updateProfileInformation', 'phone');
});

it('creates parent accounts with the parent role and requires PDPA consent', function () {
    $input = [
        'name' => 'Ibu Contoh',
        'email' => 'ibu@example.com',
        'phone' => '012 345 6789',
        'password' => 'rahsia123',
        'password_confirmation' => 'rahsia123',
    ];

    expect(fn () => app(CreateNewUser::class)->create($input))
        ->toThrow(ValidationException::class);

    $user = app(CreateNewUser::class)->create($input + ['pdpa_consent' => '1'])->refresh();

    expect($user->role)->toBe(Role::Parent)
        ->and($user->phone)->toBe('0123456789')
        ->and($user->hasVerifiedEmail())->toBeFalse();
});
