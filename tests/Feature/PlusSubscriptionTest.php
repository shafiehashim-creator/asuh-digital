<?php

use App\Enums\OrderStatus;
use App\Filament\Pages\Settings;
use App\Mail\OrderReceipt;
use App\Models\Book;
use App\Models\Institution;
use App\Models\License;
use App\Models\Order;
use App\Models\Pass;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use App\Services\PaymentProcessor;
use App\Services\PlusOffer;
use App\Services\Purchases;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    Notification::fake();
    Mail::fake();
});

function openPlus(int $priceSen = 15900): Plan
{
    Setting::set('videos_open', true);
    Setting::set('plus_sales_open', true);
    $plan = Plan::findByCode(Plan::PAS_PLUS);
    $plan->update(['price_sen' => $priceSen]);

    return $plan;
}

function yearlyPass(User $user, string $code = Plan::PAS_TAHUNAN, ?string $startsAt = '-1 day', ?string $endsAt = '+1 year'): Pass
{
    $plan = Plan::findByCode($code);
    $order = Order::factory()->paid()->create(['user_id' => $user->id, 'plan_id' => $plan->id, 'amount_sen' => $plan->price_sen]);

    return Pass::query()->create([
        'user_id' => $user->id, 'plan_id' => $plan->id, 'order_id' => $order->id,
        'starts_at' => $startsAt ? now()->modify($startsAt) : null,
        'ends_at' => $endsAt ? now()->modify($endsAt) : null,
    ]);
}

function plusBuyer(): array
{
    return [
        'name' => 'Ibu Plus', 'email' => 'plus@example.com', 'phone' => '012 345 6789',
        'password' => 'KataLaluan2026', 'password_confirmation' => 'KataLaluan2026',
        'pdpa_consent' => '1', 'payment_method' => 'chip',
    ];
}

// --- On sale ---------------------------------------------------------------------

it('keeps Langganan Plus off sale until the videos open and it has a price', function () {
    $this->get('/beli/langganan-plus')->assertNotFound();

    Setting::set('videos_open', true);
    Setting::set('plus_sales_open', true);
    $this->get('/beli/langganan-plus')->assertNotFound(); // still no price

    Setting::set('videos_open', false);
    Plan::findByCode(Plan::PAS_PLUS)->update(['price_sen' => 15900]);
    $this->get('/beli/langganan-plus')->assertNotFound();
    $this->actingAs(User::factory()->admin()->create())->get('/beli/langganan-plus')->assertOk();
});

it('sells a new Langganan Plus that opens the videos once paid', function () {
    openPlus();

    $this->get('/beli/langganan-plus')->assertOk()->assertSee('Beli Langganan Plus')->assertSee('RM159');
    $this->post('/beli/langganan-plus', plusBuyer())->assertRedirect();

    $order = Order::query()->sole();
    $buyer = $order->user;
    expect($order->amount_sen)->toBe(15900)->and($order->upgrades_pass_id)->toBeNull()
        ->and($buyer->canWatchVideos())->toBeFalse();

    app(PaymentProcessor::class)->markPaid($order);

    $pass = $buyer->activePass();
    expect($pass->isPlus())->toBeTrue()
        ->and($buyer->canWatchVideos())->toBeTrue()
        ->and($buyer->canReadBook(Book::factory()->published()->create()))->toBeTrue();
    Mail::assertQueued(OrderReceipt::class);
});

it('does not open the videos for Langganan Tahunan', function () {
    $user = User::factory()->create();
    yearlyPass($user);

    expect($user->canWatchVideos())->toBeFalse();
});

// --- Upgrades --------------------------------------------------------------------

it('charges the difference for the days left, rounded up to whole ringgit', function () {
    $plus = openPlus(15900); // RM159 vs RM99 = RM60 a year
    $user = User::factory()->create();
    $offer = app(PlusOffer::class);

    $full = yearlyPass($user, startsAt: '-1 day', endsAt: '+364 days');
    expect($offer->upgradePriceSen($full))->toBe(6000);

    $half = yearlyPass(User::factory()->create(), startsAt: '-182 days', endsAt: '+183 days');
    expect($offer->upgradePriceSen($half))->toBe(3100); // RM30.08 -> RM31

    $waiting = yearlyPass(User::factory()->create(), startsAt: null, endsAt: null);
    expect($offer->upgradePriceSen($waiting))->toBe(6000);

    $earlyBird = yearlyPass(User::factory()->create(), Plan::PAS_TAHUNAN_EARLY, '-1 day', '+364 days');
    expect($offer->upgradePriceSen($earlyBird))->toBe(8000); // from RM79
});

it('upgrades the current subscription in place, keeping its dates', function () {
    openPlus(15900);
    $user = User::factory()->create();
    $pass = yearlyPass($user, startsAt: '-182 days', endsAt: '+183 days');
    $endsAt = $pass->ends_at->toDateTimeString();

    $this->actingAs($user)->get('/beli/langganan-plus')->assertOk()
        ->assertSee('Naik taraf ke Langganan Plus')
        ->assertSee('RM31')
        ->assertSee('beza harga');

    $this->actingAs($user)->post('/beli/langganan-plus', ['payment_method' => 'chip'])->assertRedirect();

    $order = Order::query()->where('upgrades_pass_id', $pass->id)->sole();
    expect($order->amount_sen)->toBe(3100)->and($order->itemName())->toBe('Naik taraf ke Langganan Plus');

    app(PaymentProcessor::class)->markPaid($order);
    app(PaymentProcessor::class)->markPaid($order); // CHIP may call twice

    $pass->refresh();
    expect($pass->isPlus())->toBeTrue()
        ->and($pass->ends_at->toDateTimeString())->toBe($endsAt)
        ->and($user->passes()->count())->toBe(1)
        ->and($user->canWatchVideos())->toBeTrue();
});

it('does not sell a second full Plus to a family on Langganan Tahunan', function () {
    openPlus();
    $user = User::factory()->create();
    yearlyPass($user);

    expect(fn () => app(Purchases::class)->buyPlus($user, ['payment_method' => 'chip']))
        ->toThrow(InvalidArgumentException::class);
});

it('renews Langganan Plus after the current one ends', function () {
    openPlus();
    $user = User::factory()->create();
    $current = yearlyPass($user, Plan::PAS_PLUS, '-300 days', '+65 days');

    $this->actingAs($user)->get('/beli/langganan-plus')->assertSee('Beli Langganan Plus')->assertSee('Langganan baharu bermula sebaik');
    $this->actingAs($user)->post('/beli/langganan-plus', ['payment_method' => 'chip']);
    app(PaymentProcessor::class)->markPaid(Order::query()->where('status', OrderStatus::Pending)->sole());

    $renewal = $user->passes()->latest('id')->first();
    expect($renewal->isPlus())->toBeTrue()
        ->and($renewal->starts_at->toDateTimeString())->toBe($current->ends_at->toDateTimeString());
});

it('keeps an upgrade order apart from a new Plus order', function () {
    openPlus();
    $user = User::factory()->create();
    $pass = yearlyPass($user);
    $purchases = app(Purchases::class);

    $upgrade = $purchases->upgradeToPlus($user, ['payment_method' => 'chip']);
    $again = $purchases->upgradeToPlus($user, ['payment_method' => 'bank_transfer']);

    expect($again->id)->toBe($upgrade->id)->and($upgrade->upgrades_pass_id)->toBe($pass->id);
});

// --- Institutions and admin -------------------------------------------------------

it('opens videos to institutions whose licence includes them', function () {
    $institution = Institution::factory()->create();
    $license = License::factory()->for($institution)->create();
    expect($institution->canWatchVideos())->toBeFalse();

    $license->update(['includes_videos' => true]);
    expect($institution->fresh()->canWatchVideos())->toBeTrue();
});

it('lets the admin open the videos and price Langganan Plus', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire\Livewire::test(Settings::class)
        ->fillForm(['videos_open' => true, 'plus_price' => '149.00'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('videos_open'))->toBeTrue()
        ->and(Plan::findByCode(Plan::PAS_PLUS)->price_sen)->toBe(14900);
});

it('offers the upgrade on the account page once Plus is on sale', function () {
    $user = User::factory()->create();
    yearlyPass($user);

    $this->actingAs($user)->get('/akaun')->assertDontSee('Naik taraf');

    openPlus(15900);
    $this->actingAs($user)->get('/akaun')->assertSee('Baharu: video animasi')->assertSee('Naik taraf · RM60');
});
