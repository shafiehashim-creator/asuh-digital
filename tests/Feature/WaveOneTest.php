<?php

use App\Models\Book;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

// Landing shelves, free previews and the pass sales page.

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    Storage::fake('local');
    Storage::disk('local')->put('books/contoh.pdf', '%PDF-1.4 ujian');
});

function bookWithImages(int $pages = 8, array $attributes = []): Book
{
    $book = Book::factory()->published()->withFile()->create($attributes);
    $file = $book->files()->sole();
    foreach (range(1, $pages) as $page) {
        Storage::disk('local')->put($file->pageImagePath($page, 'webp'), 'gambar '.$page);
    }
    $file->forceFill(['page_images' => $pages, 'page_format' => 'webp', 'page_width' => 1400, 'page_height' => 1980, 'pages_ready_at' => now()])->save();

    return $book->fresh();
}

// --- Landing --------------------------------------------------------------------

it('shows book shelves on the landing page once the catalogue is open', function () {
    Book::factory()->published()->create(['title' => 'Buku Rak Utama']);

    $this->get('/')->assertDontSee('Buku Rak Utama')->assertDontSee('Buku terkini');

    Setting::set('catalog_open', true);
    Cache::flush();

    $this->get('/')->assertSee('Buku terkini')->assertSee('Buku Rak Utama')->assertSee('Pilihan kami')->assertSee('E-book Islamik');
});

it('ranks the most read books once families start reading', function () {
    Setting::set('catalog_open', true);
    Book::factory()->published()->create(['title' => 'Buku Sunyi']);
    $loved = Book::factory()->published()->create(['title' => 'Buku Digemari']);
    $loved->update(['published_at' => now()->subYear()]);
    foreach (User::factory()->count(3)->create() as $reader) {
        $loved->recordReader($reader);
    }

    $this->get('/')->assertSee('Paling popular')->assertSeeInOrder(['#1', 'Buku Digemari', 'Buku Sunyi']);
});

it('hides small family counts and shows real ones', function () {
    $this->get('/')->assertDontSee('Keluarga melanggan');

    Order::factory()->paid()->count(20)->create();
    Cache::flush();

    $this->get('/')->assertSee('Keluarga melanggan');
});

it('does not count staff previews as readers', function () {
    $book = Book::factory()->published()->create();
    $book->recordReader(User::factory()->admin()->create());
    $book->recordReader($parent = User::factory()->create());
    $book->recordReader($parent);

    expect($book->readers()->count())->toBe(1);
});

it('shows the WhatsApp button only when a number is set', function () {
    $this->get('/')->assertDontSee('wa.me', false);

    Setting::set('whatsapp_number', '60123456789');

    $this->get('/')->assertSee('https://wa.me/60123456789', false);
});

// --- Free preview ----------------------------------------------------------------

it('lets anyone read the first pages of a book with a fast version', function () {
    Setting::set('catalog_open', true);
    $book = bookWithImages(8);

    $this->get(route('catalog.show', $book))->assertSee('Baca pratonton percuma (5 halaman)');

    $response = $this->get(route('catalog.preview', $book));
    $response->assertOk()->assertSee('data-preview', false)->assertSee('Pratonton tamat');

    $urls = json_decode(html_entity_decode(Str::betweenFirst($response->getContent(), 'data-pages="', '"')), true)['urls'];
    expect($urls)->toHaveCount(5);

    $this->get($urls[4])->assertOk();
});

it('never serves preview pages past the limit or unsigned', function () {
    Setting::set('catalog_open', true);
    $book = bookWithImages(8);

    $this->get(URL::temporarySignedRoute('catalog.preview.page', now()->addMinutes(5), ['book' => $book->slug, 'page' => 6]))->assertNotFound();
    $this->get(route('catalog.preview.page', ['book' => $book->slug, 'page' => 1]))->assertForbidden();
});

it('offers no preview without a fast version, when turned off or before publishing', function () {
    Setting::set('catalog_open', true);
    $plain = Book::factory()->published()->withFile()->create();
    $this->get(route('catalog.preview', $plain))->assertNotFound();
    $this->get(route('catalog.show', $plain))->assertDontSee('Baca pratonton percuma');

    $book = bookWithImages(8);
    Setting::set('preview_pages', 0);
    $this->get(route('catalog.preview', $book))->assertNotFound();
});

it('keeps the preview hidden while the catalogue is closed', function () {
    $book = bookWithImages(8);

    $this->get(route('catalog.preview', $book))->assertNotFound();
});

it('sends readers who can already read straight to the full book', function () {
    Setting::set('catalog_open', true);
    $book = bookWithImages(8);
    $owner = User::factory()->create();
    Entitlement::query()->create(['user_id' => $owner->id, 'book_id' => $book->id]);

    $this->actingAs($owner)->get(route('catalog.show', $book))->assertSee('Baca sekarang')->assertDontSee('Baca pratonton percuma');
});

// --- Book page upsell --------------------------------------------------------------

it('pitches the pass with a per-book price and shows reader counts once meaningful', function () {
    Setting::set('catalog_open', true);
    $book = Book::factory()->published()->create(['single_price_sen' => 990]);
    Book::factory()->published()->count(3)->create();

    $this->get(route('catalog.show', $book))
        ->assertSee('Paling jimat · Langganan Tahunan')
        ->assertSee('Tempah slot saya')
        ->assertSee('dan 3 buku lain')
        ->assertSee('Beli buku ini RM9.90')
        ->assertDontSee('keluarga telah membaca');

    foreach (User::factory()->count(5)->create() as $reader) {
        $book->recordReader($reader);
    }

    $this->get(route('catalog.show', $book))->assertSee('5 keluarga telah membaca buku ini');
});

// --- Pass sales page ------------------------------------------------------------------

it('sells the pass with simple maths when buying one by one costs more', function () {
    Setting::set('catalog_open', true);
    Book::factory()->published()->count(12)->create(['single_price_sen' => 990]);

    $this->get(route('pass.show'))
        ->assertOk()
        ->assertSee('Langganan Tahunan ASUH Digital')
        ->assertSee('Matematik mudah')
        ->assertSee('RM118.80')
        ->assertSee('Anda jimat 33%')
        ->assertSee('RM79')
        ->assertSee('Tempah slot saya')
        ->assertSee('Apa anak baca, itulah yang anak ikut.');
});

it('skips the maths when it would not show a saving', function () {
    Book::factory()->published()->create(['single_price_sen' => 990]);

    $this->get(route('pass.show'))->assertOk()->assertDontSee('Matematik mudah')->assertDontSee('Apa anak baca, itulah yang anak ikut.');
});

it('sends the old "Pas" links to the new "Langganan" pages', function () {
    $this->get('/pas')->assertRedirect('/langganan')->assertStatus(301);
    $this->get('/beli/pas')->assertRedirect('/beli/langganan')->assertStatus(301);
    $this->get('/langganan')->assertOk()->assertSee('Langganan Tahunan ASUH Digital')->assertDontSee('Pas Tahunan');
});
