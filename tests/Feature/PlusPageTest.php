<?php

use App\Enums\Role;
use App\Models\Order;
use App\Models\Pass;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use Database\Seeders\PlanSeeder;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    Setting::set('catalog_open', true);
    Setting::set('videos_open', true);
    Setting::set('yearly_includes_videos', false);
    Setting::set('plus_sales_open', true);
    Plan::findByCode(Plan::PAS_PLUS)->update(['price_sen' => 14900]);
});

function plusPageFamily(string $code): User
{
    $user = User::factory()->create();
    $plan = Plan::findByCode($code);
    $order = Order::factory()->paid()->create(['user_id' => $user->id, 'plan_id' => $plan->id]);
    Pass::query()->create([
        'user_id' => $user->id, 'plan_id' => $plan->id, 'order_id' => $order->id,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(),
    ]);

    return $user;
}

it('stays hidden until the videos open and Plus has a price, except for staff', function () {
    Setting::set('videos_open', false);
    $this->get(route('plus.show'))->assertNotFound();
    $this->actingAs(User::factory()->create(['role' => Role::Admin]))->get(route('plus.show'))->assertOk();
});

it('explains Plus before sending families to the checkout form', function () {
    Video::factory()->published()->create(['title' => 'Animasi Contoh']);

    $this->get(route('plus.show'))->assertOk()
        ->assertSee('Langganan Plus ASUH Digital')
        ->assertSee('RM149')
        ->assertSee('Hanya RM50 lagi setahun, RM4.16 sebulan')
        ->assertSee('Animasi Contoh')
        ->assertSee('Baca dulu, tonton kemudian')
        ->assertSee('Langgan Plus sekarang')
        ->assertSee('href="'.route('purchase.plus').'"', false);
});

it('sends the video page and the menu to the sales page, not straight to the form', function () {
    Video::factory()->published()->create(['is_free_sample' => true]);

    $this->get(route('videos.index'))
        ->assertSee('href="'.route('plus.show').'"', false)
        ->assertDontSee('href="'.route('purchase.plus').'"', false);
});

it('offers yearly families an upgrade for the days left and Plus families Mod Anak', function () {
    $this->actingAs(plusPageFamily(Plan::PAS_TAHUNAN))->get(route('plus.show'))
        ->assertSee('Naik taraf · RM50')
        ->assertSee('bayar beza harga untuk baki hari sahaja');

    $this->actingAs(plusPageFamily(Plan::PAS_PLUS))->get(route('plus.show'))
        ->assertSee('Tonton dalam Mod Anak')
        ->assertSee('href="'.route('account.children').'"', false);
});

it('shows the page in English', function () {
    $this->get(route('locale.switch', 'en'));

    $this->get(route('plus.show'))->assertSee('ASUH Digital Plus Subscription')->assertSee('Read first, watch later');
});
