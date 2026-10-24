<?php

use App\Enums\AgeGroup;
use App\Enums\Role;
use App\Models\Book;
use App\Models\Category;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\Pass;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Storage::fake('local');
    Storage::disk('local')->put('books/contoh.pdf', '%PDF-1.4 ujian');
});

function readerWithPass(): User
{
    $user = User::factory()->create();
    $order = Order::factory()->paid()->create(['user_id' => $user->id]);
    Pass::query()->create([
        'user_id' => $user->id, 'plan_id' => $order->plan_id, 'order_id' => $order->id,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(),
    ]);

    return $user;
}

function fileUrlFor(Book $book, User $user, ?int $minutes = 5): string
{
    return URL::temporarySignedRoute('reader.file', now()->addMinutes($minutes), [
        'book' => $book->slug, 'file' => $book->files()->first()->id, 'u' => $user->id,
    ]);
}

// --- Gate ---------------------------------------------------------------------

it('hides the catalogue and reader from the public until it is opened', function () {
    $book = Book::factory()->published()->withFile()->create();

    $this->get('/buku')->assertNotFound();
    $this->get(route('catalog.show', $book))->assertNotFound();
    $this->actingAs(readerWithPass())->get(route('reader.show', $book))->assertNotFound();
    $this->get('/')->assertDontSee('Katalog</a>', false);
});

it('lets staff preview the closed catalogue with a notice', function () {
    Book::factory()->published()->create(['title' => 'Buku Pratonton']);

    $this->actingAs(User::factory()->role(Role::Editor)->create())
        ->get('/buku')
        ->assertOk()
        ->assertSee('Pratonton staf')
        ->assertSee('Buku Pratonton');
});

it('opens to everyone when the admin switches it on', function () {
    Setting::set('catalog_open', true);
    Book::factory()->published()->create(['title' => 'Buku Terbuka']);

    $this->get('/buku')->assertOk()->assertSee('Buku Terbuka')->assertDontSee('Pratonton staf');
    $this->get('/')->assertSee(route('catalog.index'), false);
});

// --- Catalogue ------------------------------------------------------------------

it('lists only published books', function () {
    Setting::set('catalog_open', true);
    Book::factory()->published()->create(['title' => 'Sudah Terbit']);
    Book::factory()->create(['title' => 'Masih Draf']);

    $this->get('/buku')->assertSee('Sudah Terbit')->assertDontSee('Masih Draf');
});

it('filters by category, age and search', function () {
    Setting::set('catalog_open', true);
    $akhlak = Category::query()->where('slug', 'akhlak')->first();
    $sirah = Category::query()->where('slug', 'sirah')->first();
    Book::factory()->published()->create(['title' => 'Buku Akhlak Kecil', 'category_id' => $akhlak->id, 'age_group' => AgeGroup::SixToNine]);
    Book::factory()->published()->create(['title' => 'Buku Sirah Besar', 'category_id' => $sirah->id, 'age_group' => AgeGroup::TenToThirteen, 'author' => 'Ustaz Hamid']);

    $this->get('/buku?kategori=akhlak')->assertSee('Buku Akhlak Kecil')->assertDontSee('Buku Sirah Besar');
    $this->get('/buku?umur=10-13')->assertSee('Buku Sirah Besar')->assertDontSee('Buku Akhlak Kecil');
    $this->get('/buku?cari=Hamid')->assertSee('Buku Sirah Besar')->assertDontSee('Buku Akhlak Kecil');
});

it('shows a book page, and 404 for unpublished books outside staff', function () {
    Setting::set('catalog_open', true);
    $published = Book::factory()->published()->create(['synopsis' => 'Kisah tentang kejujuran.']);
    $draft = Book::factory()->create();

    $this->get(route('catalog.show', $published))->assertOk()->assertSee('Kisah tentang kejujuran.')->assertSee('Dapatkan Pas Tahunan');
    $this->get(route('catalog.show', $draft))->assertNotFound();
    $this->actingAs(User::factory()->admin()->create())->get(route('catalog.show', $draft))->assertOk()->assertSee('belum diterbitkan');
});

it('offers "Baca sekarang" only to readers with access', function () {
    Setting::set('catalog_open', true);
    $book = Book::factory()->published()->withFile()->create();

    $this->actingAs(User::factory()->create())->get(route('catalog.show', $book))->assertDontSee('Baca sekarang');
    $this->actingAs(readerWithPass())->get(route('catalog.show', $book))->assertSee('Baca sekarang');
});

// --- Reader ---------------------------------------------------------------------

it('asks guests to log in before reading', function () {
    Setting::set('catalog_open', true);
    $book = Book::factory()->published()->withFile()->create();

    $this->get(route('reader.show', $book))->assertRedirect('/login');
});

it('sends readers without a pass back to the book page', function () {
    Setting::set('catalog_open', true);
    $book = Book::factory()->published()->withFile()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('reader.show', $book))
        ->assertRedirect(route('catalog.show', $book));

    $this->get(route('catalog.show', $book))->assertSee('perlukan Pas Tahunan');
});

it('opens the reader with a watermark and a signed file link for pass holders', function () {
    Setting::set('catalog_open', true);
    $book = Book::factory()->published()->withFile()->create();
    $reader = readerWithPass();

    $this->actingAs($reader)
        ->get(route('reader.show', $book))
        ->assertOk()
        ->assertSee($reader->email.' ·', false)
        ->assertSee('signature=', false)
        ->assertDontSee('books/contoh.pdf');
});

it('streams the PDF through a valid signed link for its owner', function () {
    Setting::set('catalog_open', true);
    $book = Book::factory()->published()->withFile()->create();
    $reader = readerWithPass();

    $response = $this->actingAs($reader)->get(fileUrlFor($book, $reader));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Content-Disposition'))->toContain('inline')
        ->and($response->streamedContent())->toBe('%PDF-1.4 ujian');
});

it('refuses file links that are unsigned, expired, tampered or shared', function () {
    Setting::set('catalog_open', true);
    $book = Book::factory()->published()->withFile()->create();
    $reader = readerWithPass();
    $friend = readerWithPass();

    // Unsigned
    $this->actingAs($reader)->get(route('reader.file', ['book' => $book->slug, 'file' => $book->files()->first()->id, 'u' => $reader->id]))->assertForbidden();
    // Expired
    $this->actingAs($reader)->get(fileUrlFor($book, $reader, minutes: -1))->assertForbidden();
    // Tampered (user id changed after signing)
    $this->actingAs($friend)->get(str_replace('u='.$reader->id, 'u='.$friend->id, fileUrlFor($book, $reader)))->assertForbidden();
    // Shared: a valid link opened by someone else
    $this->actingAs($friend)->get(fileUrlFor($book, $reader))->assertForbidden();
    // Guest
    auth()->logout();
    $this->get(fileUrlFor($book, $reader))->assertRedirect('/login');
});

it('stops serving the file once the pass has ended', function () {
    Setting::set('catalog_open', true);
    $book = Book::factory()->published()->withFile()->create();
    $reader = readerWithPass();
    $url = fileUrlFor($book, $reader);

    $reader->passes()->update(['ends_at' => now()->subMinute()]);

    $this->actingAs($reader)->get($url)->assertForbidden();
});

it('opens a single book for an entitlement holder', function () {
    Setting::set('catalog_open', true);
    $book = Book::factory()->published()->withFile()->create();
    $buyer = User::factory()->create();
    Entitlement::query()->create(['user_id' => $buyer->id, 'book_id' => $book->id]);

    $this->actingAs($buyer)->get(fileUrlFor($book, $buyer))->assertOk();
});

it('rate limits file requests', function () {
    Setting::set('catalog_open', true);
    $book = Book::factory()->published()->withFile()->create();
    $reader = readerWithPass();
    $url = fileUrlFor($book, $reader);

    foreach (range(1, 30) as $i) {
        $this->actingAs($reader)->get($url);
    }

    $this->actingAs($reader)->get($url)->assertStatus(429);
});

it('keeps book files out of the public folder', function () {
    expect(App\Models\BookFile::DISK)->toBe('local')
        ->and(config('filesystems.disks.local.root'))->toBe(storage_path('app/private'))
        ->and(config('filesystems.disks.local.serve'))->toBeFalse();
});
