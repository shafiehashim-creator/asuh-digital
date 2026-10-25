<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PreorderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\Preorder;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    Notification::fake();
});

function guestCheckout(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ibu Aminah',
        'email' => 'aminah@example.com',
        'phone' => '+60 12-345 6789',
        'password' => 'rahsia123',
        'password_confirmation' => 'rahsia123',
        'state' => 'Selangor',
        'children_count' => 2,
        'children_age_group' => '6-9',
        'payment_method' => 'chip',
        'pdpa_consent' => '1',
    ], $overrides);
}

it('shows the checkout form to guests', function () {
    $this->get('/pre-order')
        ->assertOk()
        ->assertSee('Tempah slot Langganan Tahunan')
        ->assertSee('Kata laluan')
        ->assertSee('RM79');
});

it('creates the account, reserves a slot and opens the payment page', function () {
    $response = $this->post('/pre-order', guestCheckout());

    $user = User::query()->where('email', 'aminah@example.com')->sole();
    $order = Order::query()->sole();
    $preorder = Preorder::query()->sole();

    $response->assertRedirect(route('preorder.pay', $order));
    $this->assertAuthenticatedAs($user);

    expect($user->role)->toBe(Role::Parent)
        ->and($user->phone)->toBe('0123456789')
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and($order->amount_sen)->toBe(7900)
        ->and($order->payment_method)->toBe(PaymentMethod::Chip)
        ->and($preorder->status)->toBe(PreorderStatus::Reserved)
        ->and($preorder->order_id)->toBe($order->id)
        ->and($preorder->state)->toBe('Selangor')
        ->and($preorder->pdpa_consented_at)->not->toBeNull()
        ->and($preorder->reserved_until->diffInMinutes(now()->addMinutes(30)))->toBeLessThan(1);

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('holds a bank transfer slot for longer', function () {
    $this->post('/pre-order', guestCheckout(['payment_method' => 'bank_transfer']));

    expect(Preorder::query()->sole()->reserved_until->diffInHours(now()->addHours(24)))->toBeLessThan(1);
});

it('charges the price set by the admin at the time of the order', function () {
    App\Models\Plan::findByCode('pas_tahunan_early')->update(['price_sen' => 6900]);

    $this->post('/pre-order', guestCheckout());

    expect(Order::query()->sole()->amount_sen)->toBe(6900);
});

it('shows every validation error at once', function () {
    $this->post('/pre-order', [])->assertSessionHasErrors([
        'name', 'email', 'phone', 'password', 'state', 'children_count',
        'children_age_group', 'payment_method', 'pdpa_consent',
    ]);

    expect(User::query()->count())->toBe(0);
});

it('requires PDPA consent', function () {
    $this->post('/pre-order', guestCheckout(['pdpa_consent' => null]))
        ->assertSessionHasErrors(['pdpa_consent' => 'Anda perlu bersetuju dengan notis privasi untuk meneruskan.']);

    expect(Order::query()->count())->toBe(0);
});

it('asks existing customers to log in instead of creating a second account', function () {
    User::factory()->create(['email' => 'aminah@example.com']);

    $this->post('/pre-order', guestCheckout())->assertSessionHasErrors('email');

    expect(Order::query()->count())->toBe(0);
});

it('never puts the password back into the form after an error', function () {
    Setting::set('preorder_slot_limit', 0);

    $this->post('/pre-order', guestCheckout());

    expect(session()->getOldInput())->not->toHaveKey('password');
});

it('refuses new reservations once all slots are taken', function () {
    Setting::set('preorder_slot_limit', 1);
    Preorder::factory()->status(PreorderStatus::Paid)->create();

    $this->post('/pre-order', guestCheckout())
        ->assertSessionHasErrors(['preorder' => 'Maaf, semua slot early bird telah ditempah.']);

    expect(User::query()->where('email', 'aminah@example.com')->exists())->toBeFalse()
        ->and(Order::query()->count())->toBe(0);
    $this->assertGuest();
});

it('gives the last slot to only one of two buyers', function () {
    Setting::set('preorder_slot_limit', 1);

    $this->post('/pre-order', guestCheckout())->assertRedirect();
    auth()->logout();
    $this->post('/pre-order', guestCheckout(['email' => 'kedua@example.com']))
        ->assertSessionHasErrors('preorder');

    expect(Preorder::query()->holdingSlot()->count())->toBe(1);
});

it('frees the slot of an expired reservation for someone else', function () {
    Setting::set('preorder_slot_limit', 1);
    Preorder::factory()->expiredReservation()->create();

    $this->post('/pre-order', guestCheckout())->assertRedirect();

    expect(Preorder::query()->holdingSlot()->count())->toBe(1);
});

it('lets a signed-in parent order without a password and fills a missing phone', function () {
    $user = User::factory()->create(['phone' => null]);

    $this->actingAs($user)
        ->post('/pre-order', [
            'phone' => '019 876 5432',
            'state' => 'Johor',
            'children_count' => 1,
            'children_age_group' => '10-13',
            'payment_method' => 'bank_transfer',
            'pdpa_consent' => '1',
        ])
        ->assertRedirect();

    expect(Preorder::query()->sole())
        ->user_id->toBe($user->id)
        ->email->toBe($user->email)
        ->phone->toBe('0198765432')
        ->and($user->fresh()->phone)->toBe('0198765432');
});

it('sends a parent with an active reservation back to it instead of booking twice', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post('/pre-order', guestCheckout(['payment_method' => 'chip']));
    $order = Order::query()->sole();

    $this->get('/pre-order')->assertRedirect(route('preorder.pay', $order));
    $this->post('/pre-order', guestCheckout())->assertRedirect(route('preorder.pay', $order));

    expect(Order::query()->count())->toBe(1);
});

it('shows the payment page only to the owner of the order', function () {
    $this->post('/pre-order', guestCheckout());
    $order = Order::query()->sole();

    $this->get(route('preorder.pay', $order))
        ->assertOk()
        ->assertSee($order->reference)
        ->assertSee('RM79.00');

    $this->actingAs(User::factory()->create())
        ->get(route('preorder.pay', $order))
        ->assertNotFound();
});

it('shows bank details from settings on a bank transfer order', function () {
    Setting::set('bank_name', 'Maybank');
    Setting::set('bank_account_number', '5140 0000 0000');

    $this->post('/pre-order', guestCheckout(['payment_method' => 'bank_transfer']));
    $order = Order::query()->sole();

    $this->get(route('preorder.pay', $order))
        ->assertSee('Maybank')
        ->assertSee('5140 0000 0000')
        ->assertSee($order->reference);
});

it('rate limits checkout submissions', function () {
    foreach (range(1, 10) as $i) {
        $this->post('/pre-order', []);
    }

    $this->post('/pre-order', [])->assertStatus(429);
});
