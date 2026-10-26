<?php

use App\Enums\AgeGroup;
use App\Enums\Role;
use App\Models\Book;
use App\Models\Category;
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
    Plan::findByCode(Plan::PAS_PLUS)->update(['price_sen' => 15900]);
});

function showcaseFamily(string $code): User
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

it('stays hidden until the videos open and Plus has a price', function () {
    Setting::set('videos_open', false);
    $this->get('/video')->assertNotFound();
    $this->get('/')->assertDontSee(route('videos.index'));

    Setting::set('videos_open', true);
    Plan::findByCode(Plan::PAS_PLUS)->update(['price_sen' => 0]);
    $this->get('/video')->assertNotFound();

    $this->actingAs(User::factory()->create(['role' => Role::Admin]))->get('/video')->assertOk();
});

it('plays free samples in full but never sends a locked video\'s id', function () {
    $free = Video::factory()->published()->create(['title' => 'Animasi Percuma', 'is_free_sample' => true]);
    $locked = Video::factory()->published()->create(['title' => 'Animasi Berkunci']);
    $draft = Video::factory()->create(['title' => 'Animasi Draf', 'is_free_sample' => true]);

    $this->get('/video')->assertOk()
        ->assertSee('Animasi Percuma')
        ->assertSee('data-video-id="'.$free->provider_video_id.'"', false)
        ->assertSee('Animasi Berkunci')
        ->assertDontSee($locked->provider_video_id)
        ->assertDontSee('Animasi Draf')
        ->assertDontSee($draft->provider_video_id)
        ->assertSee('Suka? Ada 1 lagi animasi dalam Langganan Plus.')
        ->assertSee('Langgan Plus sekarang')
        ->assertSee('href="'.route('plus.show').'"', false);
});

it('shows the trailer set by the admin', function () {
    Setting::set('plus_trailer_url', 'https://youtu.be/abcDEF12345');

    $this->get('/video')->assertSee('data-video-id="abcDEF12345"', false);
});

it('offers an upgrade to Langganan Tahunan families and Mod Anak to Plus families', function () {
    Video::factory()->published()->create();

    $this->actingAs(showcaseFamily(Plan::PAS_TAHUNAN))->get('/video')->assertSee('Naik taraf ke Langganan Plus');
    $this->actingAs(showcaseFamily(Plan::PAS_PLUS))->get('/video')
        ->assertSee('Tonton dalam Mod Anak')
        ->assertDontSee('Langgan Plus sekarang');
});

it('shows a book\'s animated version on its page', function () {
    $book = Book::factory()->published()->create();
    $video = Video::factory()->published()->create(['book_id' => $book->id, 'title' => 'Animasi Buku Ini']);

    $this->get(route('catalog.show', $book))->assertOk()
        ->assertSee('Ada versi animasi!')
        ->assertSee('Animasi Buku Ini')
        ->assertDontSee($video->provider_video_id);

    $video->update(['is_free_sample' => true]);
    $this->get(route('catalog.show', $book))
        ->assertSee('data-video-id="'.$video->provider_video_id.'"', false)
        ->assertSee('Tonton percuma bersama anak.');

    Setting::set('videos_open', false);
    $this->get(route('catalog.show', $book))->assertDontSee('Ada versi animasi!');
});

it('shows the video page in English', function () {
    Video::factory()->published()->create(['age_group' => AgeGroup::SixToNine]);
    $this->get(route('locale.switch', 'en'));

    $this->get('/video')->assertSee('ASUH animated videos')->assertSee('Subscribe to Plus now');
});

it('lets the admin mark a video as a free sample', function () {
    $video = Video::factory()->create();

    expect($video->fresh()->is_free_sample)->toBeFalse();
    $video->update(['is_free_sample' => true]);
    expect(Video::query()->freeSample()->pluck('id')->all())->toBe([$video->id]);
});

it('groups the videos by category, free and Plus together', function () {
    $prophets = Category::query()->where('slug', 'kisah-nabi')->sole();
    $manners = Category::query()->where('slug', 'akhlak')->sole();
    $prophets->update(['sort_order' => 1]);
    $manners->update(['sort_order' => 2]);

    $free = Video::factory()->published()->create(['title' => 'Nabi Percuma', 'is_free_sample' => true]);
    $free->categories()->attach($prophets);
    $locked = Video::factory()->published()->create(['title' => 'Nabi Berkunci']);
    $locked->categories()->attach($prophets);
    Video::factory()->published()->create(['title' => 'Akhlak Berkunci'])->categories()->attach($manners);
    Video::factory()->published()->create(['title' => 'Tanpa Kategori']);

    $this->get('/video')->assertOk()
        ->assertSee('href="#kategori-kisah-nabi"', false)
        ->assertSeeInOrder(['id="kategori-kisah-nabi"', 'Nabi Percuma', 'Nabi Berkunci', 'id="kategori-akhlak"', 'Akhlak Berkunci', 'id="kategori-lain"', 'Tanpa Kategori'], false)
        ->assertSee('1 percuma · 1 dalam Langganan Plus')
        ->assertDontSee($locked->provider_video_id);
});
