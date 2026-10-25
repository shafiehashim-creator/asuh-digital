<?php

use App\Enums\AgeGroup;
use App\Models\Book;
use App\Models\Child;
use App\Models\Order;
use App\Models\Pass;
use App\Models\ReadingSession;
use App\Models\Setting;
use App\Models\User;
use App\Services\ReadingLog;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Setting::set('catalog_open', true);
    Storage::fake('local');
    Storage::disk('local')->put('books/contoh.pdf', '%PDF-1.4 ujian');
});

function readingChild(): Child
{
    $parent = User::factory()->create();
    $order = Order::factory()->paid()->create(['user_id' => $parent->id]);
    Pass::query()->create([
        'user_id' => $parent->id, 'plan_id' => $order->plan_id, 'order_id' => $order->id,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(),
    ]);

    return Child::factory()->for($parent)->create(['age_group' => AgeGroup::SixToNine]);
}

function readingBook(): Book
{
    return Book::factory()->published()->withFile()->create(['age_group' => AgeGroup::SixToNine, 'title' => 'Kisah Semut']);
}

function inChildMode(Child $child): void
{
    test()->actingAs($child->user)->post("/akaun/anak/{$child->id}/mod-anak");
}

it('adds at most one minute per heartbeat interval', function () {
    $child = readingChild();
    $book = readingBook();
    inChildMode($child);

    $this->postJson("/mod-anak/bacaan/{$book->slug}", ['page' => 2, 'tick' => true])->assertOk()->assertJson(['minutes' => 1]);
    $this->postJson("/mod-anak/bacaan/{$book->slug}", ['page' => 3, 'tick' => true])->assertJson(['minutes' => 1]);

    $this->travel(ReadingLog::MIN_TICK_SECONDS)->seconds();
    $this->postJson("/mod-anak/bacaan/{$book->slug}", ['page' => 4, 'tick' => true])->assertJson(['minutes' => 2]);

    $session = ReadingSession::query()->sole();
    expect($session->minutes)->toBe(2)->and($session->last_page)->toBe(4);
});

it('saves the page without counting time when there is no tick', function () {
    $child = readingChild();
    $book = readingBook();
    inChildMode($child);

    $this->postJson("/mod-anak/bacaan/{$book->slug}", ['page' => 7])->assertJson(['minutes' => 0]);

    expect(ReadingSession::query()->sole()->last_page)->toBe(7);
});

it('caps minutes per book and day', function () {
    $child = readingChild();
    $book = readingBook();
    ReadingSession::query()->create([
        'child_id' => $child->id, 'book_id' => $book->id, 'read_on' => today(),
        'minutes' => ReadingLog::MAX_MINUTES_PER_DAY, 'last_tick_at' => now()->subHour(),
    ]);
    inChildMode($child);

    $this->postJson("/mod-anak/bacaan/{$book->slug}", ['page' => 2, 'tick' => true])
        ->assertJson(['minutes' => ReadingLog::MAX_MINUTES_PER_DAY]);
});

it('starts a new row each day', function () {
    $child = readingChild();
    $book = readingBook();
    inChildMode($child);

    $this->postJson("/mod-anak/bacaan/{$book->slug}", ['page' => 2, 'tick' => true]);
    $this->travel(1)->days();
    $this->postJson("/mod-anak/bacaan/{$book->slug}", ['page' => 5, 'tick' => true]);

    expect(ReadingSession::query()->count())->toBe(2);
});

it('records only in Mod Anak and only for shelf books', function () {
    $child = readingChild();
    $book = readingBook();
    $older = Book::factory()->published()->withFile()->create(['age_group' => AgeGroup::TenToThirteen]);

    // A parent reading for themselves is not a child's reading.
    $this->actingAs($child->user)->postJson("/mod-anak/bacaan/{$book->slug}", ['page' => 2, 'tick' => true])->assertForbidden();

    inChildMode($child);
    $this->postJson("/mod-anak/bacaan/{$older->slug}", ['page' => 2, 'tick' => true])->assertForbidden();
    $this->postJson("/mod-anak/bacaan/{$book->slug}", ['page' => 0])->assertUnprocessable();

    expect(ReadingSession::query()->count())->toBe(0);
});

it('reopens the book at the page reached and offers "Sambung membaca"', function () {
    $child = readingChild();
    $book = readingBook();
    inChildMode($child);
    $this->postJson("/mod-anak/bacaan/{$book->slug}", ['page' => 9]);

    $this->get("/baca/{$book->slug}")->assertOk()
        ->assertSee('data-start-page="9"', false)
        ->assertSee('data-progress-url="'.route('child-mode.progress', $book).'"', false);

    $this->get('/mod-anak')->assertSee('Sambung membaca')->assertSee('Halaman 9');
});

it('sends no progress from the parent\'s own reader', function () {
    $child = readingChild();
    $book = readingBook();

    $this->actingAs($child->user)->get("/baca/{$book->slug}")->assertOk()->assertDontSee('data-progress-url', false);
});

it('deletes reading data with the child profile', function () {
    $child = readingChild();
    $book = readingBook();
    inChildMode($child);
    $this->postJson("/mod-anak/bacaan/{$book->slug}", ['page' => 3, 'tick' => true]);

    $child->delete();

    expect(ReadingSession::query()->count())->toBe(0);
});
