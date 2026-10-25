<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Filament\ReportWidgets;
use App\Models\Book;
use App\Models\Institution;
use App\Models\Order;
use App\Models\PageVisit;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use App\Services\Reports\AdminReports;
use App\Services\Reports\Period;
use App\Support\SalesChannel;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    $this->travelTo('2026-10-26 12:00:00');
});

function period(string $from = '2026-10-01', string $to = '2026-10-26'): Period
{
    return new Period(Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay());
}

function paidOrder(array $attributes = []): Order
{
    return Order::factory()->paid()->create($attributes + ['paid_at' => '2026-10-20 10:00:00']);
}

// --- Visit tracking ---------------------------------------------------------------

it('records public page views with their source and device, without an IP', function () {
    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Mobile'])
        ->get('/?utm_source=Facebook&utm_medium=cpc&utm_campaign=raya2026')->assertOk();
    $this->get('/langganan')->assertOk();

    $visits = PageVisit::query()->orderBy('id')->get();
    expect($visits)->toHaveCount(2)
        ->and($visits->pluck('visitor')->unique())->toHaveCount(1)
        ->and($visits[0]->source)->toBe('facebook')
        ->and($visits[0]->medium)->toBe('cpc')
        ->and($visits[0]->campaign)->toBe('raya2026')
        ->and($visits[1]->source)->toBe('facebook') // the first source sticks for the visit
        ->and($visits[0]->device)->toBe('telefon')
        ->and($visits[1]->path)->toBe('/langganan')
        ->and(array_keys($visits[0]->getAttributes()))->not->toContain('ip');
});

it('takes the referring site when there are no UTM tags', function () {
    $this->withHeaders(['Referer' => 'https://www.google.com/search?q=asuh'])->get('/')->assertOk();

    expect(PageVisit::query()->sole()->source)->toBe('google.com');
});

it('does not count bots, staff, admin pages or failed pages', function () {
    $this->withHeaders(['User-Agent' => 'Googlebot/2.1'])->get('/')->assertOk();
    $this->get('/tiada-halaman-ini')->assertNotFound();
    $this->actingAs(User::factory()->admin()->create())->get('/');
    $this->get('/admin/laporan');

    expect(PageVisit::query()->count())->toBe(0);
});

it('credits an order to the source of the buyer\'s visit', function () {
    Setting::set('preorder_slot_limit', 0);
    $user = User::factory()->create();
    $this->actingAs($user)->get('/langganan?utm_source=tiktok&utm_medium=paid_social&utm_campaign=promo');
    $this->actingAs($user)->post('/beli/langganan', ['payment_method' => 'chip']);

    $order = Order::query()->sole();
    expect($order->source)->toBe('tiktok')->and($order->campaign)->toBe('promo')
        ->and(AdminReports::channelOf($order))->toBe(SalesChannel::ADS);
});

it('groups sources into channels', function (?string $source, ?string $medium, string $channel) {
    expect(SalesChannel::from($source, $medium))->toBe($channel);
})->with([
    [null, null, SalesChannel::DIRECT],
    ['terus', null, SalesChannel::DIRECT],
    ['facebook', 'cpc', SalesChannel::ADS],
    ['l.facebook.com', 'referral', SalesChannel::SOCIAL],
    ['instagram', null, SalesChannel::SOCIAL],
    ['google.com', 'referral', SalesChannel::SEARCH],
    ['mailchimp', 'email', SalesChannel::EMAIL],
    ['galeriilmu.com.my', 'referral', SalesChannel::REFERRAL],
]);

// --- Figures ------------------------------------------------------------------------

it('sums sales and compares with the period before', function () {
    paidOrder(['amount_sen' => 9900]);
    paidOrder(['amount_sen' => 7900, 'discount_sen' => 2000]);
    paidOrder(['amount_sen' => 5000, 'paid_at' => '2026-09-20 10:00:00']); // previous period
    Order::factory()->create(['amount_sen' => 9900, 'status' => OrderStatus::Pending]);

    $reports = app(AdminReports::class);
    $now = $reports->sales(period());

    expect($now)->toBe(['revenue' => 17800, 'orders' => 2, 'average' => 8900, 'discounts' => 2000])
        ->and($reports->sales(period()->previous())['revenue'])->toBe(5000)
        ->and(AdminReports::change(17800, 5000))->toBe(256.0);
});

it('splits daily sales by kind and lists products', function () {
    $book = Book::factory()->published()->create(['title' => 'Kisah Semut']);
    paidOrder(['amount_sen' => 9900, 'plan_id' => Plan::findByCode(Plan::PAS_TAHUNAN)->id]);
    paidOrder(['amount_sen' => 1500, 'plan_id' => null, 'book_id' => $book->id]);

    $reports = app(AdminReports::class);
    $daily = $reports->dailySales(period('2026-10-19', '2026-10-21'));
    expect($daily['labels'])->toBe(['2026-10-19', '2026-10-20', '2026-10-21'])
        ->and($daily['series']['Langganan'])->toBe([0, 9900, 0])
        ->and($daily['series']['Buku single'])->toBe([0, 1500, 0]);

    $products = $reports->products(period());
    expect($products->pluck('product')->all())->toBe(['Langganan Tahunan', 'E-book: Kisah Semut'])
        ->and($products->first()['share'])->toBe(86.8);
});

it('splits sales by channel, payment method and school', function () {
    $school = Institution::factory()->create(['name' => 'Tadika Seri Ilmu']);
    paidOrder(['amount_sen' => 7920, 'discount_sen' => 1980, 'referral_institution_id' => $school->id, 'source' => 'terus']);
    paidOrder(['amount_sen' => 9900, 'source' => 'facebook', 'medium' => 'cpc', 'campaign' => 'raya']);
    paidOrder(['amount_sen' => 0, 'payment_method' => PaymentMethod::Manual]);
    paidOrder(['amount_sen' => 9900, 'source' => null]);

    $reports = app(AdminReports::class);
    expect($reports->channels(period())->pluck('orders', 'channel')->all())->toBe([
        SalesChannel::ADS => 1, SalesChannel::DIRECT => 1, SalesChannel::SCHOOL => 1, SalesChannel::MANUAL => 1,
    ])
        ->and($reports->schools(period())->first())->toBe(['school' => 'Tadika Seri Ilmu', 'orders' => 1, 'revenue' => 7920, 'discounts' => 1980])
        ->and($reports->campaigns(period())->first()['campaign'])->toBe('raya')
        ->and($reports->paymentMethods(period())->pluck('orders', 'method')->get('Manual (oleh ASUH)'))->toBe(1);
});

it('counts new, paying and repeat customers', function () {
    $loyal = User::factory()->create(['created_at' => '2026-01-01']);
    paidOrder(['user_id' => $loyal->id, 'paid_at' => '2025-12-01 10:00:00']);
    paidOrder(['user_id' => $loyal->id]);
    $new = User::factory()->create(['created_at' => '2026-10-10']);
    paidOrder(['user_id' => $new->id]);
    User::factory()->create(['created_at' => '2026-10-11']); // signed up, not paid
    User::factory()->role(Role::Editor)->create(['created_at' => '2026-10-11']); // staff: not a customer

    $c = app(AdminReports::class)->customers(period());

    expect($c['new_accounts'])->toBe(2)
        ->and($c['buyers'])->toBe(2)
        ->and($c['first_time_buyers'])->toBe(1)
        ->and($c['repeat_buyers'])->toBe(1)
        ->and($c['conversion'])->toBe(50.0);
});

it('builds the traffic funnel', function () {
    foreach ([['a', 'home'], ['a', 'pass.show'], ['b', 'home'], ['c', 'catalog.show']] as [$visitor, $route]) {
        PageVisit::query()->create(['visited_at' => '2026-10-20 10:00', 'visitor' => str_pad($visitor, 32, 'x'), 'route' => $route, 'path' => '/', 'source' => 'terus', 'device' => 'telefon']);
    }
    Order::factory()->create(['created_at' => '2026-10-20 11:00', 'status' => OrderStatus::Pending]);
    paidOrder();

    $reports = app(AdminReports::class);
    expect(array_column($reports->funnel(period()), 'count'))->toBe([3, 2, 2, 1])
        ->and($reports->traffic(period())['visitors'])->toBe(3)
        ->and($reports->devices(period()))->toBe(['telefon' => 3]);
});

// --- The page -------------------------------------------------------------------------

it('shows the report page to the super admin only', function () {
    paidOrder(['amount_sen' => 9900]);
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin/laporan')->assertOk()->assertSee('Laporan');
    foreach ([ReportWidgets\SalesStats::class, ReportWidgets\ProductsTable::class, ReportWidgets\ChannelsTable::class,
        ReportWidgets\CustomerStats::class, ReportWidgets\TrafficStats::class, ReportWidgets\FunnelTable::class, ReportWidgets\SalesChart::class] as $widget) {
        Livewire::test($widget, ['pageFilters' => ['startDate' => '2026-10-01', 'endDate' => '2026-10-26']])->assertOk();
    }
    Livewire::test(ReportWidgets\ProductsTable::class, ['pageFilters' => ['startDate' => '2026-10-01', 'endDate' => '2026-10-26']])
        ->assertSee('RM99.00');

    $this->actingAs(User::factory()->role(Role::Editor)->create())->get('/admin/laporan')->assertForbidden();
});
