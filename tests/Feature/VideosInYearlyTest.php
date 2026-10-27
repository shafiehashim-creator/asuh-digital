<?php

use App\Enums\AgeGroup;
use App\Enums\PreorderStatus;
use App\Models\Book;
use App\Models\Child;
use App\Models\Order;
use App\Models\Pass;
use App\Models\Plan;
use App\Models\Preorder;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use App\Services\PlusOffer;
use Database\Seeders\PlanSeeder;

// User decision 2026-10-27: no separate Plus; every family subscription includes the videos.

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    Setting::set('catalog_open', true);
    Setting::set('videos_open', true);
});

function videoFamily(string $code): User
{
    $user = User::factory()->create();
    $plan = Plan::findByCode($code);
    $order = Order::factory()->paid()->create(['user_id' => $user->id, 'plan_id' => $plan->id]);
    Pass::query()->create([
        'user_id' => $user->id, 'plan_id' => $plan->id, 'order_id' => $order->id,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(),
    ]);

    return $user;
}

it('opens the videos for every family subscription: early bird, yearly and monthly', function (string $code) {
    expect(videoFamily($code)->canWatchVideos())->toBeTrue()
        ->and(User::factory()->create()->canWatchVideos())->toBeFalse();
})->with([Plan::PAS_TAHUNAN_EARLY, Plan::PAS_TAHUNAN, Plan::PAS_BULANAN]);

it('lets a yearly family watch in Mod Anak', function () {
    $user = videoFamily(Plan::PAS_TAHUNAN);
    $child = Child::factory()->for($user)->create(['age_group' => AgeGroup::SixToNine]);
    Video::factory()->published()->create(['title' => 'Animasi Tahunan', 'age_group' => AgeGroup::SixToNine]);

    $this->actingAs($user)->post("/akaun/anak/{$child->id}/mod-anak");
    $this->get('/mod-anak')->assertOk()->assertSee('Animasi Tahunan');
});

it('shows the videos publicly without a Plus price, and sends everyone to the yearly subscription', function () {
    expect(Plan::findByCode(Plan::PAS_PLUS)->price_sen)->toBe(0);
    Video::factory()->published()->create(['title' => 'Animasi Contoh', 'is_free_sample' => true]);

    $this->get(route('videos.index'))->assertOk()
        ->assertSee('Animasi Contoh')
        ->assertSee('Langgan sekarang')
        ->assertSee(route('pass.show'), false)
        ->assertDontSee('Langganan Plus');

    $this->get(route('plus.show'))->assertRedirect(route('pass.show'));
    $this->get(route('purchase.plus'))->assertRedirect(route('pass.show'));
    $this->post(route('plus.waitlist'), ['email' => 'a@contoh.my'])->assertRedirect(route('pass.show'));
    $this->get('/')->assertDontSee(route('plus.show'));

    expect(app(PlusOffer::class)->available())->toBeFalse();
});

it('tells the sales page the videos are included', function () {
    Book::factory()->published()->count(2)->create();
    Video::factory()->published()->count(3)->create();

    $this->get(route('pass.show'))->assertOk()
        ->assertSee('Semua 2 e-book Islamik dan 3 video animasi ASUH')
        ->assertSee('3 video animasi termasuk')
        ->assertSee('Video animasi kisah buku');

    Setting::set('videos_open', false);
    $this->get(route('pass.show'))->assertOk()->assertDontSee('video animasi termasuk');
});

it('says on a book page that its animation comes with the yearly subscription', function () {
    $book = Book::factory()->published()->create();
    Video::factory()->published()->create(['book_id' => $book->id]);

    $this->get(route('catalog.show', $book))->assertOk()
        ->assertSee('dalam Langganan Tahunan bersama semua e-book ASUH');
});

it('raises the yearly price from RM99 to RM149, but keeps a price the admin set by hand', function () {
    $migration = require database_path('migrations/2026_10_27_000002_raise_yearly_price_to_149.php');
    $yearly = Plan::findByCode(Plan::PAS_TAHUNAN);

    $yearly->update(['price_sen' => 9900]);
    $migration->up();
    expect($yearly->fresh()->price_sen)->toBe(14900);

    $yearly->update(['price_sen' => 12900]);
    $migration->up();
    expect($yearly->fresh()->price_sen)->toBe(12900);
});

it('sells the yearly subscription at RM149 once the early bird is over', function () {
    expect(Plan::findByCode(Plan::PAS_TAHUNAN)->price_sen)->toBe(14900);
    Setting::set('preorder_slot_limit', 1);
    Preorder::factory()->status(PreorderStatus::Paid)->create();

    $this->get(route('pass.show'))->assertOk()
        ->assertSeeInOrder(['Langganan 12 bulan', 'RM12.41', 'sebulan', 'Dibayar sekali setahun:', 'RM149']);
});
