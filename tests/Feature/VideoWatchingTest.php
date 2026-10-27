<?php

use App\Enums\AgeGroup;
use App\Enums\Role;
use App\Models\Book;
use App\Models\Child;
use App\Models\Classroom;
use App\Models\Institution;
use App\Models\License;
use App\Models\Order;
use App\Models\Pass;
use App\Models\Plan;
use App\Models\ReadingSession;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoView;
use Database\Seeders\PlanSeeder;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    Setting::set('catalog_open', true);
    Setting::set('videos_open', true);
    Setting::set('yearly_includes_videos', false);
    Setting::set('plus_sales_open', true);
});

function childWithPlan(string $code = Plan::PAS_PLUS, array $child = []): Child
{
    $parent = User::factory()->create();
    $plan = Plan::findByCode($code);
    $order = Order::factory()->paid()->create(['user_id' => $parent->id, 'plan_id' => $plan->id]);
    Pass::query()->create([
        'user_id' => $parent->id, 'plan_id' => $plan->id, 'order_id' => $order->id,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(),
    ]);

    return Child::factory()->for($parent)->create($child + ['nickname' => 'Aisyah', 'age_group' => AgeGroup::SixToNine]);
}

function watchAs(Child $child): void
{
    test()->actingAs($child->user)->post("/akaun/anak/{$child->id}/mod-anak")->assertRedirect('/mod-anak');
}

function tick(Video $video, int $seconds, int $position = 30, bool $ended = false)
{
    return test()->postJson("/mod-anak/video/{$video->slug}/tonton", ['seconds' => $seconds, 'position' => $position, 'ended' => $ended]);
}

// --- Access ------------------------------------------------------------------------

it('shows videos for the child\'s age to Langganan Plus families', function () {
    $child = childWithPlan();
    $mine = Video::factory()->published()->create(['title' => 'Animasi Adik', 'age_group' => AgeGroup::SixToNine]);
    Video::factory()->published()->create(['title' => 'Animasi Abang', 'age_group' => AgeGroup::TenToThirteen]);
    Video::factory()->create(['title' => 'Animasi Draf', 'age_group' => AgeGroup::SixToNine]);
    watchAs($child);

    $this->get('/mod-anak')->assertOk()
        ->assertSee('Video animasi')
        ->assertSee('Animasi Adik')
        ->assertDontSee('Animasi Abang')
        ->assertDontSee('Animasi Draf')
        // The provider's id is not on the shelf.
        ->assertDontSee($mine->provider_video_id);

    $this->get("/mod-anak/video/{$mine->slug}")->assertOk()
        ->assertSee('data-video-id="'.$mine->provider_video_id.'"', false)
        ->assertSee('Tekan untuk menonton');
});

it('keeps videos from Langganan Tahunan families', function () {
    $child = childWithPlan(Plan::PAS_TAHUNAN);
    $video = Video::factory()->published()->create(['age_group' => AgeGroup::SixToNine]);
    watchAs($child);

    $this->get('/mod-anak')->assertDontSee('Video animasi');
    $this->get("/mod-anak/video/{$video->slug}")->assertNotFound();
    tick($video, 30)->assertForbidden();
});

it('keeps videos hidden until the admin opens them', function () {
    Setting::set('videos_open', false);
    $child = childWithPlan();
    $video = Video::factory()->published()->create(['age_group' => AgeGroup::SixToNine]);
    watchAs($child);

    $this->get('/mod-anak')->assertDontSee('Video animasi');
    $this->get("/mod-anak/video/{$video->slug}")->assertNotFound();
});

it('refuses videos for another age', function () {
    $child = childWithPlan();
    $video = Video::factory()->published()->create(['age_group' => AgeGroup::TenToThirteen]);
    watchAs($child);

    $this->get("/mod-anak/video/{$video->slug}")->assertNotFound();
});

it('opens videos to pupils only when the licence includes them', function () {
    $institution = Institution::factory()->create();
    $license = License::factory()->for($institution)->create();
    $classroom = Classroom::factory()->for($institution)->create(['code' => 'AB7K2Q']);
    $pupil = new Child(['nickname' => 'Hana', 'age_group' => AgeGroup::SixToNine, 'avatar' => 'kucing']);
    $pupil->classroom_id = $classroom->id;
    $pupil->save();
    $video = Video::factory()->published()->create(['age_group' => AgeGroup::SixToNine]);

    $this->post('/kelas', ['code' => 'AB7K2Q']);
    $this->post("/kelas/pilih/{$pupil->id}");
    $this->get("/mod-anak/video/{$video->slug}")->assertNotFound();

    $license->update(['includes_videos' => true]);
    $this->get("/mod-anak/video/{$video->slug}")->assertOk();
});

// --- Read first, daily limit --------------------------------------------------------

it('locks a book\'s video until the child reads the book when the parent asks', function () {
    $child = childWithPlan(child: ['read_before_watch' => true]);
    $book = Book::factory()->published()->create(['title' => 'Kisah Semut']);
    $video = Video::factory()->published()->create(['book_id' => $book->id, 'age_group' => AgeGroup::SixToNine]);
    $standalone = Video::factory()->published()->create(['title' => 'Doa Makan', 'age_group' => AgeGroup::SixToNine]);
    watchAs($child);

    $this->get('/mod-anak')->assertSee('Baca buku &quot;Kisah Semut&quot; dahulu', false);
    $this->get("/mod-anak/video/{$video->slug}")->assertRedirect('/mod-anak');
    $this->get("/mod-anak/video/{$standalone->slug}")->assertOk();

    ReadingSession::query()->create(['child_id' => $child->id, 'book_id' => $book->id, 'read_on' => today(), 'minutes' => 4]);
    $this->get("/mod-anak/video/{$video->slug}")->assertOk();
});

it('stops at the parent\'s daily limit', function () {
    $child = childWithPlan(child: ['daily_video_minutes' => 15]);
    $video = Video::factory()->published()->create(['age_group' => AgeGroup::SixToNine]);
    VideoView::query()->create(['child_id' => $child->id, 'video_id' => $video->id, 'watched_on' => today(), 'seconds' => 14 * 60 + 40, 'last_tick_at' => now()->subMinute()]);
    watchAs($child);

    $this->get('/mod-anak')->assertSee('Baki masa menonton hari ini: 1 minit');
    $this->get("/mod-anak/video/{$video->slug}")->assertOk()->assertSee('data-seconds-left="20"', false);

    tick($video, 30)->assertJson(['seconds_left' => 0]);
    expect(VideoView::query()->sole()->seconds)->toBe(15 * 60);

    $this->get("/mod-anak/video/{$video->slug}")->assertRedirect('/mod-anak');
    $this->get('/mod-anak')->assertSee('Masa menonton hari ini sudah habis');

    $this->travel(1)->days();
    $this->get("/mod-anak/video/{$video->slug}")->assertOk();
});

// --- Recording ---------------------------------------------------------------------

it('counts no more time than really passed', function () {
    $this->freezeTime(); // only the travel below may count, never wall-clock seconds
    $child = childWithPlan();
    $video = Video::factory()->published()->create(['age_group' => AgeGroup::SixToNine]);
    watchAs($child);

    tick($video, 30)->assertOk()->assertJson(['seconds_left' => null]);
    tick($video, 30); // straight away: no time has passed
    $this->travel(10)->seconds();
    tick($video, 500); // capped at the time passed

    expect(VideoView::query()->sole()->seconds)->toBe(40);
});

it('remembers where the child stopped and when a video was finished', function () {
    $child = childWithPlan();
    $video = Video::factory()->published()->create(['age_group' => AgeGroup::SixToNine]);
    watchAs($child);

    tick($video, 30, position: 95);
    $this->get("/mod-anak/video/{$video->slug}")->assertSee('data-start="95"', false);

    $this->travel(30)->seconds();
    tick($video, 30, position: 240, ended: true);
    expect(VideoView::query()->sole()->completed)->toBeTrue();
    $this->get("/mod-anak/video/{$video->slug}")->assertSee('data-start="0"', false);
});

it('deletes watching records with the child profile', function () {
    $child = childWithPlan();
    $video = Video::factory()->published()->create();
    VideoView::query()->create(['child_id' => $child->id, 'video_id' => $video->id, 'watched_on' => today(), 'seconds' => 60]);

    $child->delete();

    expect(VideoView::query()->count())->toBe(0);
});

// --- Parent settings and staff preview ------------------------------------------

it('lets parents set the daily limit and "Baca dulu" on the child profile', function () {
    $child = childWithPlan();
    $this->actingAs($child->user);

    $this->get("/akaun/anak/{$child->id}/ubah")->assertOk()->assertSee('Had menonton sehari')->assertSee('Baca dulu, tonton kemudian');

    $this->put("/akaun/anak/{$child->id}", [
        'nickname' => 'Aisyah', 'age_group' => '6-9', 'avatar' => 'anis',
        'video_settings' => '1', 'daily_video_minutes' => '30', 'read_before_watch' => '1',
    ])->assertRedirect('/akaun/anak');
    expect($child->refresh()->daily_video_minutes)->toBe(30)->and($child->read_before_watch)->toBeTrue();

    $this->put("/akaun/anak/{$child->id}", [
        'nickname' => 'Aisyah', 'age_group' => '6-9', 'avatar' => 'anis',
        'video_settings' => '1', 'daily_video_minutes' => '', 'read_before_watch' => '0',
    ]);
    expect($child->refresh()->daily_video_minutes)->toBeNull()->and($child->read_before_watch)->toBeFalse();

    $this->put("/akaun/anak/{$child->id}", ['nickname' => 'Aisyah', 'age_group' => '6-9', 'avatar' => 'anis', 'daily_video_minutes' => '7'])
        ->assertSessionHasErrors('daily_video_minutes');
});

it('hides the video settings while videos are closed', function () {
    Setting::set('videos_open', false);
    $child = childWithPlan();

    $this->actingAs($child->user)->get("/akaun/anak/{$child->id}/ubah")->assertDontSee('Had menonton sehari');
});

it('lets staff preview unpublished videos without recording anything', function () {
    $video = Video::factory()->create();

    $this->actingAs(User::factory()->role(Role::Editor)->create())
        ->get("/admin/video-pratonton/{$video->slug}")
        ->assertOk()
        ->assertSee('Pratonton staf')
        ->assertSee('data-progress-url=""', false);

    $this->actingAs(User::factory()->create())->get("/admin/video-pratonton/{$video->slug}")->assertForbidden();
});
