<?php

use App\Enums\CampaignOffer;
use App\Enums\Role;
use App\Filament\Resources\Campaigns\Pages\ManageCampaigns;
use App\Models\Campaign;
use App\Models\Order;
use App\Models\Pass;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use App\Services\PlusOffer;
use Database\Seeders\PlanSeeder;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(PlanSeeder::class));

function campaign(array $overrides = []): Campaign
{
    return Campaign::query()->create(array_merge([
        'title' => 'Jualan PBAKL',
        'message' => 'Diskaun istimewa sempena PBAKL.',
        'cta_label' => 'Langgan sekarang',
        'cta_url' => '/langganan',
        'promo_code' => 'PBAKL20',
        'ends_at' => now()->addDays(2)->addHours(3),
    ], $overrides));
}

it('shows the bar on every public page and the big countdown on the sales pages', function () {
    // Dates are stored to the second.
    $this->freezeSecond();
    campaign();

    $this->get('/tentang-kami')->assertOk()
        ->assertSee('data-campaign-bar', false)
        ->assertSee('Jualan PBAKL')
        ->assertSee('Tamat dalam')
        ->assertSee('2 hari')
        ->assertSee('PBAKL20')
        ->assertDontSee('data-campaign-banner', false);

    foreach (['/', route('pass.show')] as $url) {
        $this->get($url)->assertOk()
            ->assertSee('data-campaign-banner', false)
            ->assertSee('Diskaun istimewa sempena PBAKL.')
            ->assertSee('href="/langganan"', false)
            ->assertSee('countdown('.(2 * 86400 + 3 * 3600), false);
    }
});

it('shows nothing before it starts, after it ends or when switched off', function () {
    campaign(['starts_at' => now()->addDay(), 'show_coming_soon' => false]);
    campaign(['ends_at' => now()->subMinute()]);
    campaign(['is_active' => false]);

    $this->get('/')->assertDontSee('Jualan PBAKL')->assertDontSee('data-campaign-bar', false);
});

it('follows the places chosen and shows the campaign ending first', function () {
    campaign(['title' => 'Kempen Lama', 'ends_at' => now()->addDays(10), 'show_banner' => false]);
    campaign(['title' => 'Hujung Minggu', 'ends_at' => now()->addDay(), 'show_bar' => false]);

    $this->get('/')
        ->assertSee('Kempen Lama')
        ->assertSee('Hujung Minggu')
        ->assertSeeInOrder(['data-campaign-bar', 'Kempen Lama', 'data-campaign-banner', 'Hujung Minggu'], false);
});

it('uses the English text when the visitor reads in English', function () {
    campaign(['title_en' => 'PBAKL Sale', 'message_en' => null]);

    $this->get(route('locale.switch', 'en'));
    $this->get('/')
        ->assertSee('PBAKL Sale')
        ->assertSee('Diskaun istimewa sempena PBAKL.')
        ->assertSee('Ends in');
});

it('lets a Super Admin create a campaign, and only a Super Admin', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageCampaigns::class)
        ->callAction('create', data: [
            'title' => 'Pelancaran ASUH', 'promo_code' => 'launch10', 'cta_url' => '/langganan',
            'ends_at' => now()->addWeek()->format('Y-m-d H:i'), 'show_bar' => true, 'show_banner' => true, 'is_active' => true,
        ])
        ->assertHasNoActionErrors();

    expect(Campaign::query()->sole())->promo_code->toBe('LAUNCH10');
    $this->get('/admin/kempen')->assertOk()->assertSee('Pelancaran ASUH')->assertSee('Berjalan');

    Livewire::test(ManageCampaigns::class)
        ->callAction('create', data: ['title' => 'Salah', 'cta_url' => 'javascript:alert(1)', 'ends_at' => now()->addDay()->format('Y-m-d H:i')])
        ->assertHasActionErrors(['cta_url']);

    $this->actingAs(User::factory()->role(Role::Editor)->create())->get('/admin/kempen')->assertForbidden();
});

it('announces a campaign as coming soon, counting down to its start', function () {
    $this->freezeSecond();
    campaign(['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(3)]);

    $this->get('/')
        ->assertSee('Akan datang')
        ->assertSee('Bermula dalam')
        ->assertSee('Anda masih boleh membeli sekarang pada harga biasa.')
        ->assertSee('countdown(86400, true)', false)
        // The code is only for when it runs.
        ->assertDontSee('PBAKL20');

    Campaign::query()->update(['show_coming_soon' => false]);
    $this->get('/')->assertDontSee('Jualan PBAKL');
});

it('sells the early bird only inside an early bird campaign, the normal price before and after', function () {
    // No early bird campaign: the early bird sells as before.
    $this->get(route('purchase.pass'))->assertRedirect(route('preorder.create'));

    campaign(['title' => 'Pre-order early bird', 'offer' => CampaignOffer::EarlyBird, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(8)]);

    // Coming soon: the normal price is on sale and the pre-order is closed.
    $this->get('/')->assertSee('Akan datang')->assertSee('RM79')->assertSee('RM99')->assertDontSee('Pre-order · Harga early bird');
    $this->get(route('preorder.create'))->assertRedirect(route('purchase.pass'));
    $this->get(route('purchase.pass'))->assertOk()->assertSee('RM99');

    $this->travel(2)->days();
    $this->get('/')->assertSee('Pre-order · Harga early bird')->assertSee('Tamat dalam');
    $this->get(route('purchase.pass'))->assertRedirect(route('preorder.create'));
    $this->get(route('preorder.create'))->assertOk();

    $this->travel(7)->days();
    $this->get('/')->assertDontSee('Pre-order · Harga early bird')->assertDontSee('Pre-order early bird');
    $this->get(route('preorder.create'))->assertRedirect(route('purchase.pass'));
});

it('sells Langganan Plus at the campaign price while it runs, for new and upgrading families', function () {
    Setting::set('catalog_open', true);
    Setting::set('videos_open', true);
    Plan::findByCode(Plan::PAS_PLUS)->update(['price_sen' => 14900]);
    campaign(['title' => 'Promosi Plus', 'offer' => CampaignOffer::PlusPromo, 'promo_price_sen' => 12900, 'ends_at' => now()->addDays(5)]);

    $this->get(route('plus.show'))->assertSee('RM129')->assertSee('RM149');
    $this->get(route('purchase.plus'))->assertSee('RM129')->assertSee('RM149');

    $this->post(route('purchase.plus.store'), [
        'name' => 'Ibu Plus', 'email' => 'plus@example.com', 'phone' => '012 345 6789',
        'password' => 'rahsia123', 'password_confirmation' => 'rahsia123',
        'payment_method' => 'chip', 'pdpa_consent' => '1', 'tempoh' => 'tahunan',
    ])->assertRedirect();
    expect(Order::query()->sole()->amount_sen)->toBe(12900);

    // A yearly family upgrades for the difference against the campaign price.
    $user = User::factory()->create();
    $plan = Plan::findByCode(Plan::PAS_TAHUNAN);
    $pass = Pass::query()->create(['user_id' => $user->id, 'plan_id' => $plan->id, 'order_id' => Order::factory()->paid()->create(['user_id' => $user->id, 'plan_id' => $plan->id])->id, 'starts_at' => null, 'ends_at' => null]);
    expect(app(PlusOffer::class)->upgradePriceSen($pass))->toBe(3000);

    $this->travel(6)->days();
    $this->get(route('plus.show'))->assertSee('RM149')->assertDontSee('RM129');
});

it('asks for the Plus price below the normal one', function () {
    Plan::findByCode(Plan::PAS_PLUS)->update(['price_sen' => 14900]);
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageCampaigns::class)
        ->callAction('create', data: ['title' => 'Plus', 'offer' => 'plus', 'promo_price_sen' => '149', 'ends_at' => now()->addDay()->format('Y-m-d H:i')])
        ->assertHasActionErrors(['promo_price_sen']);

    Livewire::test(ManageCampaigns::class)
        ->callAction('create', data: ['title' => 'Plus', 'offer' => 'plus', 'promo_price_sen' => '119.90', 'show_coming_soon' => false, 'ends_at' => now()->addDay()->format('Y-m-d H:i')])
        ->assertHasNoActionErrors();
    expect(Campaign::query()->sole()->promo_price_sen)->toBe(11990);
});
