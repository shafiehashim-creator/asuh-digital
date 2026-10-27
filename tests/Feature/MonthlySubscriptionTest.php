<?php

use App\Enums\PreorderStatus;
use App\Mail\RenewalReminder;
use App\Models\Book;
use App\Models\Institution;
use App\Models\Order;
use App\Models\Pass;
use App\Models\Plan;
use App\Models\Preorder;
use App\Models\Setting;
use App\Models\User;
use App\Services\PaymentProcessor;
use App\Services\PlusOffer;
use App\Services\SubscriptionPlans;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    Notification::fake();
    Mail::fake();
});

function monthlyOnSale(): void
{
    // The early bird is over, so regular (and monthly) sales are open.
    Setting::set('preorder_slot_limit', 1);
    Preorder::factory()->status(PreorderStatus::Paid)->create();
}

function monthlyBuyer(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ibu Bulanan', 'email' => 'bulanan@example.com', 'phone' => '012 345 6789',
        'password' => 'rahsia123', 'password_confirmation' => 'rahsia123',
        'payment_method' => 'chip', 'pdpa_consent' => '1',
    ], $overrides);
}

function monthlyPassFor(User $user, string $code, string $endsIn): Pass
{
    $plan = Plan::findByCode($code);
    $order = Order::factory()->paid()->create(['user_id' => $user->id, 'plan_id' => $plan->id]);

    return Pass::query()->create([
        'user_id' => $user->id, 'plan_id' => $plan->id, 'order_id' => $order->id,
        'starts_at' => now()->subDays(20), 'ends_at' => now()->modify($endsIn),
    ]);
}

it('offers yearly first as the better deal, with monthly beside it', function () {
    monthlyOnSale();

    $this->get(route('purchase.pass'))->assertOk()
        ->assertSeeInOrder(['Paling berbaloi · Jimat 45%', 'Tahunan', 'RM99', 'Bulanan', 'RM14.90'])
        ->assertSee('name="tempoh" value="tahunan" checked', false);

    $this->get(route('purchase.pass', ['tempoh' => 'bulanan']))
        ->assertSee('name="tempoh" value="bulanan" checked', false);

    expect(SubscriptionPlans::yearlySavingPercent(Plan::findByCode(Plan::PAS_TAHUNAN), Plan::findByCode(Plan::PAS_BULANAN)))->toBe(45);
});

it('sells a one-month subscription paid by hand', function () {
    monthlyOnSale();

    $this->post(route('purchase.pass.store'), monthlyBuyer(['tempoh' => 'bulanan']))->assertRedirect();

    $order = Order::query()->sole();
    expect($order->plan->code)->toBe(Plan::PAS_BULANAN)->and($order->amount_sen)->toBe(1490);

    app(PaymentProcessor::class)->markPaid($order);
    $pass = Pass::query()->sole();

    expect((int) round($pass->starts_at->diffInDays($pass->ends_at)))->toBeBetween(28, 31)
        ->and($order->user->canReadBook(Book::factory()->published()->create()))->toBeTrue();
});

it('keeps buying yearly by default and hides monthly when its price is 0', function () {
    monthlyOnSale();
    Plan::findByCode(Plan::PAS_BULANAN)->update(['price_sen' => 0]);

    $this->get(route('purchase.pass'))->assertDontSee('value="bulanan"', false);
    $this->get(route('pass.show'))->assertDontSee('Atau langgan bulanan');

    $this->post(route('purchase.pass.store'), monthlyBuyer(['tempoh' => 'bulanan']))->assertSessionHasErrors('purchase');
    $this->post(route('purchase.pass.store'), monthlyBuyer())->assertRedirect();
    expect(Order::query()->sole()->plan->code)->toBe(Plan::PAS_TAHUNAN);
});

it('shows the monthly option on the sales pages only after the early bird', function () {
    $this->get(route('pass.show'))->assertDontSee('Atau langgan bulanan');

    monthlyOnSale();
    $this->get(route('pass.show'))
        ->assertSee('Jimat 45% berbanding bayar bulanan')
        ->assertSee('Atau langgan bulanan, RM14.90 sebulan')
        ->assertSee(route('purchase.pass', ['tempoh' => 'bulanan']), false);
});

it('keeps school codes for yearly subscriptions only', function () {
    monthlyOnSale();
    $school = Institution::factory()->create();

    $this->post(route('purchase.pass.store'), monthlyBuyer(['tempoh' => 'bulanan', 'promo_code' => $school->parent_code]))
        ->assertSessionHasErrors('promo_code');
    expect(Order::query()->count())->toBe(0);
});

it('starts yearly after the current month when a monthly family switches', function () {
    monthlyOnSale();
    $user = User::factory()->create();
    $month = monthlyPassFor($user, Plan::PAS_BULANAN, '+10 days');

    $this->actingAs($user)->post(route('purchase.pass.store'), ['payment_method' => 'chip', 'tempoh' => 'tahunan']);
    $order = Order::query()->where('plan_id', Plan::findByCode(Plan::PAS_TAHUNAN)->id)->sole();
    app(PaymentProcessor::class)->markPaid($order);

    $yearly = Pass::query()->where('order_id', $order->id)->sole();
    expect($yearly->starts_at->equalTo($month->ends_at))->toBeTrue();

    $this->actingAs($user)->get(route('account.billing'))->assertOk();
});

it('lets monthly families buy Plus but not upgrade a month to it', function () {
    Setting::set('videos_open', true);
    Setting::set('yearly_includes_videos', false);
    Setting::set('plus_sales_open', true);
    Plan::findByCode(Plan::PAS_PLUS)->update(['price_sen' => 15900]);
    Plan::findByCode(Plan::PAS_PLUS_BULANAN)->update(['price_sen' => 2490]);
    $user = User::factory()->create();
    monthlyPassFor($user, Plan::PAS_BULANAN, '+10 days');

    expect(app(PlusOffer::class)->upgradablePass($user))->toBeNull();

    $plusMonth = User::factory()->create();
    monthlyPassFor($plusMonth, Plan::PAS_PLUS_BULANAN, '+10 days');
    expect($plusMonth->canWatchVideos())->toBeTrue();

    $this->get(route('purchase.plus'))->assertOk()->assertSee('RM24.90');
});

it('reminds monthly families once, 3 days before the end, unless already renewed', function () {
    $soon = User::factory()->create();
    $soonPass = monthlyPassFor($soon, Plan::PAS_BULANAN, '+2 days');

    $later = User::factory()->create();
    monthlyPassFor($later, Plan::PAS_BULANAN, '+10 days');

    $renewed = User::factory()->create();
    monthlyPassFor($renewed, Plan::PAS_BULANAN, '+2 days');
    monthlyPassFor($renewed, Plan::PAS_TAHUNAN, '+1 year');

    $yearly = User::factory()->create();
    monthlyPassFor($yearly, Plan::PAS_TAHUNAN, '+2 days');

    $this->artisan('asuh:renewal-reminders')->assertSuccessful();
    $this->artisan('asuh:renewal-reminders')->assertSuccessful();

    Mail::assertQueued(RenewalReminder::class, 1);
    Mail::assertQueued(RenewalReminder::class, fn (RenewalReminder $mail) => $mail->hasTo($soon->email));

    $html = (new RenewalReminder($soonPass->fresh()))->render();
    expect($html)->toContain('Tukar ke tahunan dan jimat 45%')->toContain('tempoh=bulanan');
});

it('invites monthly families in their account to switch to yearly', function () {
    Setting::set('catalog_open', true);
    $user = User::factory()->create();
    monthlyPassFor($user, Plan::PAS_BULANAN, '+10 days');

    $this->actingAs($user)->get(route('account.billing'))->assertOk()
        ->assertSee('Langganan Bulanan')
        ->assertSee('Tukar ke tahunan (lebih jimat)')
        ->assertSee('Jimat 45% dengan tahunan');
});
