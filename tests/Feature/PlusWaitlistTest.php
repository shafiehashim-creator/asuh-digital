<?php

use App\Filament\Pages\Settings;
use App\Mail\PlusNowOpen;
use App\Models\Book;
use App\Models\Order;
use App\Models\Pass;
use App\Models\Plan;
use App\Models\PlusWaitlistEntry;
use App\Models\Setting;
use App\Models\User;
use App\Services\Purchases;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    Setting::set('catalog_open', true);
    Setting::set('videos_open', true);
    Plan::findByCode(Plan::PAS_PLUS)->update(['price_sen' => 14900]);
});

function waitlistYearlyFamily(): User
{
    $user = User::factory()->create();
    $plan = Plan::findByCode(Plan::PAS_TAHUNAN);
    $order = Order::factory()->paid()->create(['user_id' => $user->id, 'plan_id' => $plan->id]);
    Pass::query()->create([
        'user_id' => $user->id, 'plan_id' => $plan->id, 'order_id' => $order->id,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(),
    ]);

    return $user;
}

it('shows Plus but takes sign-ups instead of orders until sales open', function () {
    Setting::set('plus_opens_on', '2026-12-01');

    $this->get(route('plus.show'))->assertOk()
        ->assertSee('Langganan Plus dibuka pada 1 Disember 2026')
        ->assertSee(route('plus.waitlist'), false)
        ->assertSee('Langgan Tahunan sekarang')
        ->assertDontSee(route('purchase.plus'), false);

    $this->get(route('purchase.plus'))->assertRedirect(route('plus.show'));
    expect(fn () => app(Purchases::class)->buyPlus(null, []))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(Purchases::class)->upgradeToPlus(waitlistYearlyFamily(), []))->toThrow(InvalidArgumentException::class);
});

it('says soon when no opening date is set, and hides the Plus monthly option', function () {
    Plan::findByCode(Plan::PAS_PLUS_BULANAN)->update(['price_sen' => 2490, 'is_active' => true]);

    $this->get(route('plus.show'))->assertOk()
        ->assertSee('Langganan Plus dibuka tidak lama lagi')
        ->assertDontSee('langgan bulanan');
});

it('does not offer the upgrade to yearly families while Plus is not sold', function () {
    $user = waitlistYearlyFamily();

    $this->actingAs($user)->get(route('account'))->assertOk()->assertDontSee('Naik taraf ·');
    $this->actingAs($user)->get(route('video.index'))->assertOk()->assertSee('Beritahu saya bila Plus dibuka');

    Setting::set('plus_sales_open', true);
    $this->actingAs($user)->get(route('account'))->assertOk()->assertSee('Naik taraf ·');
});

it('remembers each e-mail once, with the visitor language', function () {
    $this->post(route('plus.waitlist'), ['email' => 'Ibu@Contoh.my'])
        ->assertRedirect(route('plus.show').'#beritahu')
        ->assertSessionHas('plus_waitlist');
    $this->post(route('plus.waitlist'), ['email' => 'ibu@contoh.my']);
    $this->post(route('plus.waitlist'), ['email' => 'bukan-emel'])->assertSessionHasErrors('email');

    expect(PlusWaitlistEntry::count())->toBe(1)
        ->and(PlusWaitlistEntry::first()->email)->toBe('ibu@contoh.my')
        ->and(PlusWaitlistEntry::first()->locale)->toBe('ms');
});

it('e-mails the waiting list once when the admin opens Plus sales', function () {
    Mail::fake();
    PlusWaitlistEntry::create(['email' => 'a@contoh.my', 'locale' => 'ms']);
    PlusWaitlistEntry::create(['email' => 'b@contoh.my', 'locale' => 'en']);

    $this->actingAs(User::factory()->admin()->create());
    Livewire::test(Settings::class)->fillForm(['plus_sales_open' => true])->call('save')->assertHasNoFormErrors();

    Mail::assertQueued(PlusNowOpen::class, 2);
    Mail::assertQueued(PlusNowOpen::class, fn ($mail) => $mail->hasTo('b@contoh.my') && $mail->locale === 'en');
    expect(PlusWaitlistEntry::whereNull('notified_at')->count())->toBe(0);

    // Saving again does not e-mail anyone twice.
    Livewire::test(Settings::class)->call('save');
    Mail::assertQueued(PlusNowOpen::class, 2);

    $this->get(route('purchase.plus'))->assertOk();
});

it('sends subscription buttons to the sales page before the checkout', function () {
    $book = Book::factory()->published()->create(['single_price_sen' => 1500]);

    $this->get(route('landing'))->assertOk()->assertSee('href="'.route('pass.show').'"', false);
    $this->get(route('catalog.show', $book))->assertOk()
        ->assertSee('href="'.route('pass.show').'" class="btn-gelap mt-3 w-full"', false);
    $this->actingAs(User::factory()->create())->get(route('account'))->assertOk()
        ->assertSee('href="'.route('pass.show').'"', false)
        ->assertDontSee('href="'.route('purchase.pass').'"', false);
});
