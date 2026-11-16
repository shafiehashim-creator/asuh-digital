<?php

use App\Enums\AgeGroup;
use App\Enums\Role;
use App\Filament\Resources\Books\Pages\CreateBook;
use App\Filament\Resources\BookSeries\Pages\EditBookSeries;
use App\Filament\Resources\BookSeries\RelationManagers\BooksRelationManager;
use App\Models\Book;
use App\Models\BookSeries;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use App\Services\BlogBooks;
use App\Services\ChildShelf;
use Livewire\Livewire;

beforeEach(function () {
    Setting::set('catalog_open', true);
});

/** @return list<Book> published books of a new series, in the given positions */
function seriesBooks(BookSeries $series, array $positions, string $prefix = 'Rasul'): array
{
    return collect($positions)->map(fn (int $position) => Book::factory()->published()->create([
        'title' => "{$prefix} {$position}", 'age_group' => AgeGroup::SixToNine,
        'book_series_id' => $series->id, 'series_position' => $position,
    ]))->all();
}

it('shows a series as one card in the catalogue and every book when searching', function () {
    $series = BookSeries::factory()->create(['name' => 'Kisah Menarik 25 Rasul']);
    seriesBooks($series, [2, 1, 3]);
    Book::factory()->published()->create(['title' => 'Buku Sendiri']);
    // A series with only one published book stays a normal book card.
    $lonely = BookSeries::factory()->create(['name' => 'Siri Sunyi']);
    seriesBooks($lonely, [1], 'Sunyi');

    $this->get('/buku')->assertOk()
        ->assertSee('Kisah Menarik 25 Rasul')
        ->assertSee(route('series.show', $series), false)
        ->assertSee('3 buku')
        ->assertDontSee('Rasul 2')
        ->assertSee('Buku Sendiri')
        ->assertSee('Sunyi 1');

    $this->get('/buku?cari=Rasul')->assertOk()
        ->assertSee('Rasul 1')->assertSee('Rasul 2')->assertSee('Rasul 3');
});

it('lists the published books of a series in reading order', function () {
    $series = BookSeries::factory()->create(['name' => 'Siri Komik', 'description' => 'Pengenalan siri.']);
    seriesBooks($series, [3, 1, 2], 'Komik');
    Book::factory()->create(['title' => 'Komik Draf', 'book_series_id' => $series->id, 'series_position' => 4]);

    $this->get(route('series.show', $series))->assertOk()
        ->assertSeeInOrder(['Siri Komik', 'Pengenalan siri.', 'Komik 1', 'Komik 2', 'Komik 3'])
        ->assertDontSee('Komik Draf');

    // Staff also see drafts; a series with nothing published is hidden from everyone else.
    $this->actingAs(User::factory()->role(Role::Editor)->create())->get(route('series.show', $series))->assertSee('Komik Draf');
    auth()->logout();
    $empty = BookSeries::factory()->create();
    Book::factory()->create(['book_series_id' => $empty->id]);
    $this->get(route('series.show', $empty))->assertNotFound();
});

it('shows the rest of the series and the next book on a book page', function () {
    $series = BookSeries::factory()->create(['name' => 'Siri Nabi']);
    [$one, $two, $three] = seriesBooks($series, [1, 2, 3], 'Nabi');

    $this->get(route('catalog.show', $two))->assertOk()
        ->assertSee('Siri Nabi · Buku 2')
        ->assertSee('Seterusnya: Nabi 3')
        ->assertSeeInOrder(['Nabi 1', 'Nabi 2', 'Nabi 3']);

    $this->get(route('catalog.show', $three))->assertOk()->assertDontSee('Seterusnya:');
});

it('gives each series its own Mod Anak row, first and in reading order', function () {
    $category = Category::query()->where('slug', 'akhlak')->first();
    Book::factory()->published()->count(12)->create(['age_group' => AgeGroup::SixToNine])
        ->each(fn (Book $book) => $book->categories()->sync([$category->id]));
    $series = BookSeries::factory()->create(['name' => 'Siri Anis']);
    seriesBooks($series, [2, 1], 'Anis');

    $rows = app(ChildShelf::class)->rows(Book::with(['categories', 'series'])->get(), collect());

    expect($rows[0]['title'])->toBe('Siri Anis')
        ->and(collect($rows[0]['items'])->map(fn ($item) => $item['book']->title)->all())->toBe(['Anis 1', 'Anis 2'])
        ->and(collect($rows)->flatMap(fn ($row) => $row['items'])->filter(fn ($item) => str_starts_with($item['book']->title, 'Anis')))->toHaveCount(2);
});

it('puts a new book in a series from the book form and lets staff order a series', function () {
    $this->actingAs(User::factory()->role(Role::Editor)->create());
    $series = BookSeries::factory()->create();
    [$first] = seriesBooks($series, [1]);

    Livewire::test(CreateBook::class)
        ->fillForm([
            'title' => 'Sambungan', 'author' => 'Pak Cik Bad', 'synopsis' => 'Kisah seterusnya.',
            'age_group' => '6-9', 'categories' => [Category::query()->value('id')],
            'book_series_id' => $series->id,
        ])
        ->assertSchemaStateSet(['series_position' => 2])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Book::query()->where('title', 'Sambungan')->value('series_position'))->toBe(2);

    $loose = Book::factory()->create(['title' => 'Buku Lepas']);
    Livewire::test(BooksRelationManager::class, ['ownerRecord' => $series, 'pageClass' => EditBookSeries::class])
        ->assertCanSeeTableRecords([$first])
        ->callTableAction('associate', data: ['recordId' => $loose->id]);

    expect($loose->fresh()->book_series_id)->toBe($series->id)
        ->and($loose->fresh()->series_position)->toBe(3);
});

it('reads the book number from blog titles', function (string $title, ?int $number) {
    expect(BlogBooks::seriesNumber($title))->toBe($number);
})->with([
    ['Kisah Menarik 25 Rasul (Buku 4)', 4],
    ['KGS 2023: Celak Ajaib (No 6)', 6],
    ['Catatan Tadabbur Quran@114 Jilid 11', 11],
    ['YEAH, DAH SOLAT 2 ', 2],
    ['Yeah, Dah Solat!', null],
]);
