<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Filament\Pages\Royalties as RoyaltiesPage;
use App\Models\Book;
use App\Models\Child;
use App\Models\Classroom;
use App\Models\Contributor;
use App\Models\Order;
use App\Models\Pass;
use App\Models\Plan;
use App\Models\ReadingSession;
use App\Models\Setting;
use App\Models\User;
use App\Services\Royalties;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    Setting::set('royalty_pool_percent', 30);
    Setting::set('royalty_min_minutes', 5);
    Setting::set('royalty_single_percent', 10);
    Setting::set('royalty_plus_ebook_share', 60);
    $this->month = Carbon::parse('2027-03-01');
});

function paidPass(string $plan, int $sen, string $starts): Order
{
    $order = Order::factory()->create([
        'plan_id' => Plan::findByCode($plan)->id, 'amount_sen' => $sen, 'status' => OrderStatus::Paid, 'paid_at' => Carbon::parse($starts),
    ]);
    Pass::query()->create(['user_id' => $order->user_id, 'plan_id' => $order->plan_id, 'order_id' => $order->id,
        'starts_at' => Carbon::parse($starts), 'ends_at' => Carbon::parse($starts)->addYear()]);

    return $order;
}

function readFor(Book $book, int $minutes, ?Child $child = null, string $on = '2027-03-10'): void
{
    ReadingSession::query()->create(['child_id' => ($child ?? Child::factory()->create())->id, 'book_id' => $book->id, 'read_on' => $on, 'minutes' => $minutes, 'last_page' => 1]);
}

it('spreads a year of subscription over its days, and counts only the e-book share of Plus', function () {
    paidPass(Plan::PAS_TAHUNAN, 36500, '2027-01-01'); // RM365 a year: RM1 a day
    paidPass(Plan::PAS_PLUS, 36500, '2027-01-01');    // 60% e-book: RM0.60 a day
    paidPass(Plan::PAS_TAHUNAN, 36500, '2027-03-16'); // half of March

    $revenue = app(Royalties::class)->recognisedRevenue($this->month->copy()->startOfMonth(), $this->month->copy()->endOfMonth());

    // 31 days × RM1 + 31 × RM0.60 + 16 days × about RM1 (that pass runs through 29 Feb 2028),
    // give or take a few sen for the last second of the month.
    expect($revenue)->toBeGreaterThanOrEqual(6550)->toBeLessThanOrEqual(6562);
});

it('counts a read once per child and book a month, from the minimum minutes, without pupils', function () {
    $book = Book::factory()->published()->create();
    $child = Child::factory()->create();

    readFor($book, 3, $child, '2027-03-02');
    readFor($book, 3, $child, '2027-03-03'); // 6 minutes in the month: one read
    readFor($book, 4);                        // too short
    readFor($book, 30, null, '2027-02-27');  // other month

    $pupil = Child::factory()->create(['user_id' => null, 'classroom_id' => Classroom::factory()]);
    readFor($book, 30, $pupil);

    $reads = app(Royalties::class)->reads($this->month->copy()->startOfMonth(), $this->month->copy()->endOfMonth());
    expect($reads[$book->id])->toBe(1);
});

it('splits the pool by reads, adds single sales and pays contributors by share', function () {
    paidPass(Plan::PAS_TAHUNAN, 36500, '2027-01-01');
    $popular = Book::factory()->published()->create(['title' => 'Popular']);
    $quiet = Book::factory()->published()->create(['title' => 'Sunyi']);
    foreach (range(1, 3) as $i) {
        readFor($popular, 10);
    }
    readFor($quiet, 10);
    Order::factory()->create(['book_id' => $quiet->id, 'plan_id' => null, 'amount_sen' => 1000, 'status' => OrderStatus::Paid, 'paid_at' => '2027-03-05']);

    $writer = Contributor::factory()->create(['name' => 'Penulis A']);
    $artist = Contributor::factory()->create(['name' => 'Pelukis B']);
    $popular->contributors()->attach($writer, ['role' => 'penulis', 'share_percent' => 70]);
    $popular->contributors()->attach($artist, ['role' => 'ilustrator', 'share_percent' => 30]);
    $quiet->contributors()->attach($writer, ['role' => 'penulis', 'share_percent' => 100]);

    $report = app(Royalties::class)->forMonth($this->month);
    $pool = $report['pool_sen'];
    $byTitle = $report['books']->keyBy(fn ($r) => $r['book']->title);

    expect($pool)->toBe((int) round($report['revenue_sen'] * 0.3))
        ->and($byTitle['Popular']['pool_sen'])->toBe((int) round($pool * 0.75))
        ->and($byTitle['Sunyi']['single_sen'])->toBe(100)
        ->and($byTitle['Popular']['shares_ok'])->toBeTrue();

    $people = $report['contributors']->keyBy('name');
    expect($people['Pelukis B']['total_sen'])->toBe((int) round($byTitle['Popular']['total_sen'] * 0.3))
        ->and($people['Penulis A']['books'])->toBe(2)
        ->and($people['Penulis A']['role'])->toBe('Penulis');
});

it('uses a book\'s own single royalty rate when its contract differs', function () {
    $special = Book::factory()->published()->create(['royalty_single_percent' => 25]);
    $normal = Book::factory()->published()->create();
    foreach ([$special, $normal] as $book) {
        Order::factory()->create(['book_id' => $book->id, 'plan_id' => null, 'amount_sen' => 990, 'status' => OrderStatus::Paid, 'paid_at' => '2027-03-05']);
    }

    $rows = app(Royalties::class)->forMonth($this->month)['books']->keyBy(fn ($r) => $r['book']->id);

    expect($rows[$special->id]['single_sen'])->toBe(248) // 25% of RM9.90
        ->and($rows[$normal->id]['single_sen'])->toBe(99); // the 10% default

    // Only Super Admins see and set the rate on the book form.
    $this->actingAs(User::factory()->admin()->create())->get("/admin/buku/{$special->slug}/edit")->assertSee('Kadar royalti khas jualan single');
    $this->actingAs(User::factory()->role(Role::Editor)->create())->get("/admin/buku/{$special->slug}/edit")->assertDontSee('Kadar royalti khas jualan single');
});

it('shows the report and downloads CSV for Super Admins only', function () {
    paidPass(Plan::PAS_TAHUNAN, 9900, now()->subMonths(2)->toDateString());
    $book = Book::factory()->published()->create(['title' => 'Kisah Semut']);
    readFor($book, 10, null, now()->subMonthNoOverflow()->startOfMonth()->addDays(3)->toDateString());

    $this->actingAs(User::factory()->admin()->create());
    $this->get('/admin/royalti')->assertOk()->assertSee('Kisah Semut')->assertSee('Belum ada penerima royalti untuk buku ini.');

    Livewire::test(RoyaltiesPage::class)->set('poolPercent', 50)->assertSee('Kolam royalti (50%)')
        ->callAction('csvBooks')->assertFileDownloaded('royalti-buku-'.now()->subMonthNoOverflow()->format('Y-m').'.csv');

    $this->actingAs(User::factory()->role(Role::Editor)->create())->get('/admin/royalti')->assertForbidden();
});
