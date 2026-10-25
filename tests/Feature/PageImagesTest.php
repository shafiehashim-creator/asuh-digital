<?php

use App\Enums\Role;
use App\Filament\Resources\Books\Pages\CreateBook;
use App\Filament\Resources\Books\Pages\PreparePages;
use App\Models\Book;
use App\Models\BookFile;
use App\Models\Category;
use App\Models\Entitlement;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

// "Versi pantas": page images made in the admin's browser and served to readers.

beforeEach(function () {
    Storage::fake('local');
    Storage::disk('local')->put('books/contoh.pdf', '%PDF-1.4 ujian');
    Setting::set('catalog_open', true);
    $this->editor = User::factory()->role(Role::Editor)->create();
});

function bookWithPdf(): BookFile
{
    return Book::factory()->published()->withFile()->create()->files()->sole();
}

function uploadPages(BookFile $file, int $pages): void
{
    test()->post(route('admin.pages.start', $file))->assertOk();
    foreach (range(1, $pages) as $page) {
        test()->post(route('admin.pages.store', ['file' => $file, 'page' => $page]), [
            'image' => UploadedFile::fake()->image("$page.webp", 140, 200),
        ])->assertOk();
    }
}

function markReady(BookFile $file, int $pages): BookFile
{
    foreach (range(1, $pages) as $page) {
        Storage::disk('local')->put($file->pageImagePath($page, 'webp'), 'gambar '.$page);
    }
    $file->forceFill(['page_images' => $pages, 'page_format' => 'webp', 'page_width' => 1400, 'page_height' => 1980, 'pages_ready_at' => now()])->save();

    return $file;
}

function pageUrl(BookFile $file, User $user, int $page, int $minutes = 5): string
{
    return URL::temporarySignedRoute('reader.page', now()->addMinutes($minutes), [
        'book' => $file->book->slug, 'file' => $file->id, 'u' => $user->id, 'page' => $page,
    ]);
}

it('lets staff upload page images and marks the fast version ready', function () {
    $file = bookWithPdf();
    $this->actingAs($this->editor);

    uploadPages($file, 3);
    $this->post(route('admin.pages.finish', $file), ['pages' => 3, 'format' => 'webp', 'width' => 1400, 'height' => 1980])->assertOk();

    $file->refresh();
    expect($file->hasPageImages())->toBeTrue()
        ->and($file->page_images)->toBe(3)
        ->and($file->page_format)->toBe('webp');
    Storage::disk('local')->assertExists($file->pageImagePath(3));
    Storage::disk('public')->assertMissing($file->pageImagePath(1));
});

it('refuses to finish while pages are missing', function () {
    $file = bookWithPdf();
    $this->actingAs($this->editor);

    uploadPages($file, 2);
    $this->post(route('admin.pages.finish', $file), ['pages' => 3, 'format' => 'webp', 'width' => 1400, 'height' => 1980])->assertStatus(422);

    expect($file->refresh()->hasPageImages())->toBeFalse();
});

it('only accepts images from staff', function () {
    $file = bookWithPdf();

    $this->actingAs(User::factory()->create())
        ->post(route('admin.pages.store', ['file' => $file, 'page' => 1]), ['image' => UploadedFile::fake()->image('1.webp')])
        ->assertForbidden();
    $this->get(route('admin.pages.pdf', $file))->assertForbidden();

    $this->actingAs($this->editor)
        ->post(route('admin.pages.store', ['file' => $file, 'page' => 1]), ['image' => UploadedFile::fake()->create('1.exe', 10, 'application/x-msdownload')])
        ->assertSessionHasErrors('image');
});

it('drops the images when the PDF is replaced', function () {
    $file = markReady(bookWithPdf(), 2);

    $file->update(['path' => 'books/baharu.pdf']);

    expect($file->refresh()->hasPageImages())->toBeFalse();
    Storage::disk('local')->assertMissing('book-pages/'.$file->id.'/1.webp');
});

it('gives the reader signed page image links once the fast version is ready', function () {
    $file = markReady(bookWithPdf(), 2);
    $reader = User::factory()->create();
    Entitlement::query()->create(['user_id' => $reader->id, 'book_id' => $file->book_id]);

    $this->actingAs($reader)->get(route('reader.show', $file->book))
        ->assertOk()
        ->assertSee('data-pages=', false)
        ->assertSee('halaman', false);

    $response = $this->get(pageUrl($file, $reader, 2));
    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('image/webp')
        ->and($response->getFile()->getContent())->toBe('gambar 2');
});

it('keeps using the PDF until the fast version is ready', function () {
    $file = bookWithPdf();
    $reader = User::factory()->create();
    Entitlement::query()->create(['user_id' => $reader->id, 'book_id' => $file->book_id]);

    $this->actingAs($reader)->get(route('reader.show', $file->book))->assertOk()->assertDontSee('data-pages=', false);
    $this->get(pageUrl($file, $reader, 1))->assertNotFound();
});

it('protects page images like the PDF', function () {
    $file = markReady(bookWithPdf(), 2);
    $owner = User::factory()->create();
    Entitlement::query()->create(['user_id' => $owner->id, 'book_id' => $file->book_id]);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(pageUrl($file, $owner, 1))->assertForbidden(); // shared link
    $this->actingAs($stranger)->get(pageUrl($file, $stranger, 1))->assertForbidden(); // no access
    $this->actingAs($owner)->get(pageUrl($file, $owner, 1, minutes: -1))->assertForbidden(); // expired
    $this->actingAs($owner)->get(pageUrl($file, $owner, 3))->assertNotFound(); // past the last page
});

it('opens the fast version page right after a book with a PDF is created', function () {
    $this->actingAs($this->editor);

    Livewire::test(CreateBook::class)
        ->fillForm([
            'title' => 'Buku Pantas',
            'author' => 'Penulis',
            'category_id' => Category::query()->value('id'),
            'age_group' => '6-9',
            'synopsis' => 'Sinopsis',
            'files' => [['edition' => 'original', 'path' => [UploadedFile::fake()->create('buku.pdf', 300, 'application/pdf')]]],
        ])
        ->call('create')
        ->assertRedirect(PreparePages::getUrl(['record' => Book::query()->where('title', 'Buku Pantas')->sole(), 'auto' => 1]));

    $this->get(PreparePages::getUrl(['record' => Book::query()->where('title', 'Buku Pantas')->sole()]))
        ->assertOk()
        ->assertSee('Buat versi pantas');
});
