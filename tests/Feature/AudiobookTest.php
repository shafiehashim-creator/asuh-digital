<?php

use App\Enums\AgeGroup;
use App\Enums\AudioStatus;
use App\Enums\Role;
use App\Filament\Resources\Books\Pages\EditBook;
use App\Filament\Resources\Books\RelationManagers\PagesRelationManager;
use App\Jobs\AlignPageAudio;
use App\Jobs\GeneratePageAudio;
use App\Models\Book;
use App\Models\Child;
use App\Models\Order;
use App\Models\Pass;
use App\Models\Setting;
use App\Models\User;
use App\Services\Audio\BookAudio;
use App\Services\Audio\ElevenLabs;
use App\Services\Audio\WordMatcher;
use App\Services\ChildMode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    Storage::disk('local')->put('books/contoh.pdf', '%PDF-1.4 ujian');
    Setting::set('catalog_open', true);
    config(['services.elevenlabs.key' => null, 'services.elevenlabs.url' => 'https://api.elevenlabs.io']);
});

function audioFamily(): User
{
    $user = User::factory()->create();
    $order = Order::factory()->paid()->create(['user_id' => $user->id]);
    Pass::query()->create([
        'user_id' => $user->id, 'plan_id' => $order->plan_id, 'order_id' => $order->id,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(),
    ]);

    return $user;
}

/** A published book whose page 1 has a voice and word times. */
function audioBook(bool $published = true): Book
{
    $book = Book::factory()->published()->withFile()->create(['audio_published_at' => $published ? now() : null]);
    Storage::disk('local')->put("book-audio/{$book->id}/1.mp3", file_get_contents(base_path('tests/Fixtures/page.mp3')));
    $book->pages()->create([
        'page' => 1, 'text' => 'Anis suka membaca', 'audio_path' => "book-audio/{$book->id}/1.mp3", 'audio_status' => AudioStatus::Ready,
        'timings' => [['t' => 'Anis', 's' => 0.0, 'e' => 0.4], ['t' => 'suka', 's' => 0.5, 'e' => 0.8], ['t' => 'membaca', 's' => 0.9, 'e' => 1.5]],
        'word_boxes' => [['t' => 'Anis', 'x' => 0.1, 'y' => 0.8, 'w' => 0.1, 'h' => 0.03], ['t' => 'suka', 'x' => 0.22, 'y' => 0.8, 'w' => 0.1, 'h' => 0.03], ['t' => 'membaca.', 'x' => 0.34, 'y' => 0.8, 'w' => 0.15, 'h' => 0.03]],
    ]);

    return $book;
}

// --- Word times -----------------------------------------------------------------

it('puts spoken word times on the printed words, even when the lists differ a little', function () {
    $spoken = [['t' => 'Anis', 's' => 0, 'e' => 1], ['t' => 'dan', 's' => 1, 'e' => 2], ['t' => 'Anas', 's' => 2, 'e' => 3], ['t' => 'makan.', 's' => 3, 'e' => 4]];
    $boxes = [['t' => '“Anis', 'x' => .1, 'y' => .1, 'w' => .1, 'h' => .1], ['t' => 'Anas', 'x' => .3, 'y' => .1, 'w' => .1, 'h' => .1], ['t' => 'makan', 'x' => .5, 'y' => .1, 'w' => .1, 'h' => .1]];

    $matched = WordMatcher::attachBoxes($spoken, $boxes);

    expect(array_column($matched, 'b'))->toBe([[0.1, 0.1, 0.1, 0.1], null, [0.3, 0.1, 0.1, 0.1], [0.5, 0.1, 0.1, 0.1]])
        ->and(WordMatcher::coverage($matched))->toBe(0.75);
});

it('turns the character times of an AI voice into words', function () {
    $words = ElevenLabs::wordsFromCharacters(str_split('Jom baik'), [0, .1, .2, .3, .4, .5, .6, .7], [.1, .2, .3, .4, .5, .6, .7, .8]);

    expect($words)->toBe([['t' => 'Jom', 's' => 0.0, 'e' => 0.3], ['t' => 'baik', 's' => 0.4, 'e' => 0.8]]);
});

it('reads the page number from audio file names', function (string $name, ?int $page) {
    expect(BookAudio::pageFromFileName($name))->toBe($page);
})->with([['halaman-03.mp3', 3], ['p12.mp3', 12], ['Buku 2 hal 7.mp3', 7], ['kulit.mp3', null]]);

// --- Reader ------------------------------------------------------------------------

it('gives readers the 🎧 voice and word times only once audio is open and the book published', function () {
    $book = audioBook();
    $family = audioFamily();

    $this->actingAs($family)->get(route('reader.show', $book))->assertOk()->assertDontSee('data-audio=', false);

    Setting::set('audio_open', true);
    $page = $this->actingAs($family)->get(route('reader.show', $book))->assertOk()->assertSee('data-audio=', false)->assertSee('🎧 Dengar');
    $audio = json_decode(html_entity_decode(str($page->getContent())->match('/data-audio="([^"]+)"/')), true);
    expect($audio[1]['words'][2])->toMatchArray(['t' => 'membaca', 'b' => [0.34, 0.8, 0.15, 0.03]]);

    $this->get($audio[1]['url'])->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
    // The link is bound to the reader it was made for.
    $this->actingAs(audioFamily())->get($audio[1]['url'])->assertForbidden();

    $book->update(['audio_published_at' => null]);
    $this->actingAs($family)->get(route('reader.show', $book))->assertDontSee('data-audio=', false);
    // Staff can always try it.
    $this->actingAs(User::factory()->role(Role::Editor)->create())->get(route('reader.show', $book))->assertSee('data-audio=', false);
});

it('lets parents turn listening off for a child in Mod Anak', function () {
    Setting::set('audio_open', true);
    $book = audioBook();
    $family = audioFamily();
    $child = Child::factory()->for($family)->create(['age_group' => $book->age_group === AgeGroup::Both ? '6-9' : $book->age_group->value]);
    $this->actingAs($family);
    app(ChildMode::class)->enter($child);

    $this->get(route('child-mode.home'))->assertSee('title="Boleh didengar"', false);
    $this->get(route('reader.show', $book))->assertSee('data-audio=', false);

    $child->update(['allow_audio' => false]);
    $this->get(route('child-mode.home'))->assertDontSee('title="Boleh didengar"', false);
    $this->get(route('reader.show', $book))->assertDontSee('data-audio=', false);
});

// --- Admin -------------------------------------------------------------------------

it('lets staff set up pages, upload voices matched by file name and publish', function () {
    Queue::fake();
    config(['services.elevenlabs.key' => 'ujian']);
    $book = Book::factory()->published()->withFile()->create();
    $book->files()->first()->update(['page_count' => 3]);
    $this->actingAs(User::factory()->admin()->create());

    $manager = Livewire::test(PagesRelationManager::class, ['ownerRecord' => $book, 'pageClass' => EditBook::class])
        ->callTableAction('setUp');
    expect($book->pages()->count())->toBe(3);
    $book->pages()->where('page', 2)->update(['text' => 'Anis suka membaca']);

    $mp3 = fn (string $name) => UploadedFile::fake()->createWithContent($name, file_get_contents(base_path('tests/Fixtures/page.mp3')));
    $manager->callTableAction('uploadMany', data: ['files' => [$mp3('halaman-02.mp3'), $mp3('kulit.mp3')]])
        ->assertNotified('1 fail disimpan');

    $page = $book->pages()->where('page', 2)->first();
    expect($page->audio_status)->toBe(AudioStatus::Ready)
        ->and($page->audio_source)->toBe('upload')
        ->and(Storage::disk('local')->exists($page->audio_path))->toBeTrue();
    Queue::assertPushed(AlignPageAudio::class, fn ($job) => $job->page->is($page));

    $manager->callTableAction('publish');
    expect($book->fresh()->audio_published_at)->not->toBeNull();
});

it('does not let editors publish audio', function () {
    $book = audioBook(published: false);
    $this->actingAs(User::factory()->role(Role::Editor)->create());

    Livewire::test(PagesRelationManager::class, ['ownerRecord' => $book, 'pageClass' => EditBook::class])
        ->assertTableActionHidden('publish');
});

it('queues AI voices for pages with text and makes them with word times', function () {
    Queue::fake();
    config(['services.elevenlabs.key' => 'ujian']);
    $book = Book::factory()->published()->withFile()->create();
    $withText = $book->pages()->create(['page' => 1, 'text' => 'Jom baik']);
    $book->pages()->create(['page' => 2]);
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(PagesRelationManager::class, ['ownerRecord' => $book, 'pageClass' => EditBook::class])
        ->callTableAction('generateAll')
        ->assertNotified('1 halaman sedang dijana');
    Queue::assertPushed(GeneratePageAudio::class, 1);
    expect($withText->fresh()->audio_status)->toBe(AudioStatus::Queued);

    Http::fake(['api.elevenlabs.io/v1/text-to-speech/*' => Http::response([
        'audio_base64' => base64_encode('ID3 suara'),
        'alignment' => ['characters' => str_split('Jom baik'), 'character_start_times_seconds' => [0, .1, .2, .3, .4, .5, .6, .7], 'character_end_times_seconds' => [.1, .2, .3, .4, .5, .6, .7, .8]],
    ])]);
    (new GeneratePageAudio($withText->fresh()))->handle(app(BookAudio::class));

    $page = $withText->fresh();
    expect($page->audio_status)->toBe(AudioStatus::Ready)
        ->and($page->audio_source)->toBe('ai')
        ->and(Storage::disk('local')->get($page->audio_path))->toBe('ID3 suara')
        ->and(array_column($page->timings, 't'))->toBe(['Jom', 'baik'])
        ->and($page->audio_seconds)->toBe(0.8);
    Http::assertSent(fn ($request) => $request->hasHeader('xi-api-key', 'ujian') && $request['text'] === 'Jom baik');
});

it('finds when each word is said in an uploaded voice', function () {
    config(['services.elevenlabs.key' => 'ujian']);
    $book = audioBook();
    $page = $book->pages()->first();
    $page->update(['timings' => null]);
    Http::fake(['api.elevenlabs.io/v1/forced-alignment' => Http::response(['words' => [
        ['text' => 'Anis', 'start' => 0.1, 'end' => 0.5], ['text' => ' ', 'start' => 0.5, 'end' => 0.6], ['text' => 'suka', 'start' => 0.6, 'end' => 0.9],
    ]])]);

    (new AlignPageAudio($page))->handle(app(BookAudio::class));

    expect($page->fresh()->timings)->toBe([['t' => 'Anis', 's' => 0.1, 'e' => 0.5], ['t' => 'suka', 's' => 0.6, 'e' => 0.9]]);
});

it('keeps the words and their places from the PDF while versi pantas runs', function () {
    $book = Book::factory()->published()->withFile()->create();
    $file = $book->files()->first();
    $this->actingAs(User::factory()->role(Role::Editor)->create());
    $image = UploadedFile::fake()->image('1.webp', 10, 10);

    $this->post(route('admin.pages.store', ['file' => $file, 'page' => 1]), [
        'image' => $image,
        'words' => json_encode([['t' => 'Pagi', 'x' => 0.1, 'y' => 0.2, 'w' => 0.1, 'h' => 0.03], ['t' => 'itu', 'x' => 0.25, 'y' => 0.2, 'w' => 0.08, 'h' => 0.03]]),
    ])->assertOk();

    $page = $book->pages()->where('page', 1)->first();
    expect($page->text)->toBe('Pagi itu')->and($page->word_boxes)->toHaveCount(2);

    // Text tidied by staff is kept when versi pantas runs again.
    $page->update(['text' => 'Pagi itu, cerah.']);
    $this->post(route('admin.pages.store', ['file' => $file, 'page' => 1]), ['image' => $image, 'words' => json_encode([['t' => 'Pagi', 'x' => 0.1, 'y' => 0.2, 'w' => 0.1, 'h' => 0.03]])])->assertOk();
    expect($page->fresh()->text)->toBe('Pagi itu, cerah.')->and($page->fresh()->word_boxes)->toHaveCount(1);
});
