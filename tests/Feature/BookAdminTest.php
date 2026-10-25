<?php

use App\Enums\BookEdition;
use App\Enums\BookStatus;
use App\Enums\ReviewStatus;
use App\Enums\Role;
use App\Filament\Resources\Books\Pages\CreateBook;
use App\Filament\Resources\Books\Pages\EditBook;
use App\Filament\Resources\Books\Pages\ListBooks;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use App\Services\BookWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->editor = User::factory()->role(Role::Editor)->create();
});

// --- Workflow ---------------------------------------------------------------

it('needs a PDF before a book can go to syariah review', function () {
    $book = Book::factory()->create();

    expect(fn () => app(BookWorkflow::class)->submitForReview($book))->toThrow(InvalidArgumentException::class)
        ->and($book->fresh()->status)->toBe(BookStatus::Draft);
});

it('moves a book from draft to review to published', function () {
    $book = Book::factory()->withFile()->create();
    $workflow = app(BookWorkflow::class);

    $workflow->submitForReview($book);
    expect($book->fresh()->status)->toBe(BookStatus::Review)
        ->and($book->latestReview->status)->toBe(ReviewStatus::Pending);

    $review = $workflow->review($book->fresh(), $this->editor, approved: true);
    expect($review->reviewer_id)->toBe($this->editor->id)->and($review->reviewed_at)->not->toBeNull();

    $workflow->publish($book->fresh());
    expect($book->fresh()->isPublished())->toBeTrue();
});

it('sends a rejected book back to draft and requires notes', function () {
    $book = Book::factory()->withFile()->create();
    $workflow = app(BookWorkflow::class);
    $workflow->submitForReview($book);

    expect(fn () => $workflow->review($book->fresh(), $this->editor, approved: false))->toThrow(InvalidArgumentException::class);

    $workflow->review($book->fresh(), $this->editor, approved: false, notes: 'Halaman 4: semak rujukan hadis.');

    expect($book->fresh()->status)->toBe(BookStatus::Draft)
        ->and($book->fresh()->latestReview->notes)->toBe('Halaman 4: semak rujukan hadis.');
});

it('only lets staff review', function () {
    $book = Book::factory()->withFile()->create();
    app(BookWorkflow::class)->submitForReview($book);

    expect(fn () => app(BookWorkflow::class)->review($book->fresh(), User::factory()->create(), approved: true))
        ->toThrow(InvalidArgumentException::class);
});

// --- Admin panel --------------------------------------------------------------

it('lets editors and admins manage books but keeps parents out', function () {
    $this->actingAs($this->editor)->get('/admin/buku')->assertOk();
    $this->actingAs(User::factory()->admin()->create())->get('/admin/buku')->assertOk();
    $this->actingAs(User::factory()->create())->get('/admin/buku')->assertForbidden();
});

it('creates a book with a cover and a private PDF', function () {
    $this->actingAs($this->editor);

    Livewire::test(CreateBook::class)
        ->fillForm([
            'title' => 'Anis Belajar Berkongsi',
            'author' => 'Penulis Ujian',
            'categories' => [Category::query()->where('slug', 'akhlak')->value('id'), Category::query()->where('slug', 'komik')->value('id')],
            'age_group' => '6-9',
            'single_price_sen' => '9.90',
            'synopsis' => 'Anis belajar bahawa berkongsi itu indah.',
            'cover_path' => UploadedFile::fake()->image('kaver.jpg', 600, 800),
            'files' => [[
                'edition' => BookEdition::Original->value,
                'path' => [UploadedFile::fake()->create('buku.pdf', 300, 'application/pdf')],
                'page_count' => 24,
            ]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $book = Book::query()->sole();
    $file = $book->files()->sole();

    expect($book->slug)->toBe('anis-belajar-berkongsi')
        ->and($book->status)->toBe(BookStatus::Draft)
        ->and($book->single_price_sen)->toBe(990)
        ->and($file->path)->toStartWith('books/')
        ->and($file->page_count)->toBe(24)
        ->and($book->categories->pluck("slug")->sort()->values()->all())->toBe(["akhlak", "komik"]);

    Storage::disk('local')->assertExists($file->path);
    Storage::disk('public')->assertExists($book->cover_path);
    Storage::disk('public')->assertMissing($file->path);
});

it('accepts a cover of any ratio and a large PDF', function () {
    $this->actingAs($this->editor);

    Livewire::test(CreateBook::class)
        ->fillForm([
            'title' => 'Buku Besar',
            'author' => 'Penulis',
            'categories' => [Category::query()->value('id')],
            'age_group' => '6-9',
            'synopsis' => 'Sinopsis',
            'cover_path' => UploadedFile::fake()->image('kaver-segi-empat.jpg', 2000, 2000)->size(8000),
            'files' => [['edition' => 'original', 'path' => [UploadedFile::fake()->create('buku.pdf', 86000, 'application/pdf')]]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Book::query()->sole()->files()->count())->toBe(1);
});

it('caps admin uploads at 200 MB and gives slow connections enough time', function () {
    expect(config('livewire.temporary_file_upload.rules'))->toContain('max:204800')
        ->and(config('livewire.temporary_file_upload.max_upload_time'))->toBeGreaterThanOrEqual(30);
});

it('rejects files that are not PDFs', function () {
    $this->actingAs($this->editor);

    Livewire::test(CreateBook::class)
        ->fillForm([
            'title' => 'Ujian',
            'author' => 'Penulis',
            'categories' => [Category::query()->value('id')],
            'age_group' => '6-9',
            'synopsis' => 'Sinopsis',
            'files' => [['edition' => 'original', 'path' => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')]]],
        ])
        ->call('create')
        ->assertHasFormErrors();

    expect(Book::query()->count())->toBe(0);
});

it('walks a book through review and publishing from the edit page', function () {
    $this->actingAs($this->editor);
    $book = Book::factory()->withFile()->create();

    Livewire::test(EditBook::class, ['record' => $book->getRouteKey()])
        ->assertActionVisible('submitForReview')
        ->assertActionHidden('publish')
        ->callAction('submitForReview');

    Livewire::test(EditBook::class, ['record' => $book->getRouteKey()])
        ->callAction('review', data: ['decision' => 'reject', 'notes' => ''])
        ->assertHasActionErrors(['notes' => 'required']);

    Livewire::test(EditBook::class, ['record' => $book->getRouteKey()])
        ->callAction('review', data: ['decision' => 'approve']);

    Livewire::test(EditBook::class, ['record' => $book->getRouteKey()])
        ->assertActionVisible('publish')
        ->callAction('publish', data: ['published_at' => now()->toDateTimeString()]);

    expect($book->fresh()->isPublished())->toBeTrue();

    Livewire::test(EditBook::class, ['record' => $book->getRouteKey()])
        ->assertActionHidden('delete')
        ->callAction('unpublish');

    expect($book->fresh()->status)->toBe(BookStatus::Draft);
});

it('shows books awaiting review in the list', function () {
    $this->actingAs($this->editor);
    $inReview = Book::factory()->withFile()->create();
    app(BookWorkflow::class)->submitForReview($inReview);
    $draft = Book::factory()->create();

    Livewire::test(ListBooks::class)
        ->assertCanSeeTableRecords([$inReview, $draft])
        ->filterTable('status', BookStatus::Review->value)
        ->assertCanSeeTableRecords([$inReview])
        ->assertCanNotSeeTableRecords([$draft]);
});
