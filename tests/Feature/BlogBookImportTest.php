<?php

use App\Enums\BookStatus;
use App\Enums\Role;
use App\Filament\Resources\Books\Pages\CreateBook;
use App\Filament\Resources\Books\Pages\ListBooks;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use App\Services\BlogBooks;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function blogEntry(): array
{
    $html = '<div class="separator"><a href="https://blogger.googleusercontent.com/img/b/R29v/AVvX/s1108/yeah%20solat.jpg">'
        .'<img data-original-height="1108" data-original-width="781" src="https://blogger.googleusercontent.com/img/b/R29v/AVvX/s320/yeah%20solat.jpg" /></a></div>'
        ."YEAH,\nDAH SOLAT 2&nbsp;<br /><span>ISBN:\n978-629-7716-58-9<br />PENULIS: Pakcik Bad<br /></span><span>KOD:\nGB0892<br /></span>"
        .'<span>TEBAL:104 ms<br /></span><span>Harga: RM15.00/RM17.00</span><p></p>'
        .'<p><span>&nbsp;SINOPSIS</span></p><p><span>“Nampaknya Fateh belum kenal kita,” kata Syaitan Kurus.</span></p>'
        .'<p><span>Jom ikut kisah Fateh.</span></p>';

    return [
        'id' => ['$t' => 'tag:blogger.com,1999:blog-5140395345964836031.post-986341215997817318'],
        'title' => ['$t' => 'YEAH, DAH SOLAT 2 '],
        'content' => ['$t' => $html],
        'link' => [['rel' => 'alternate', 'href' => 'https://bukugaleri.blogspot.com/2025/05/yeah-dah-solat-2.html']],
        'category' => [['term' => '1 Komik Jom Jadi Baik'], ['term' => 'Pak Cik Bad']],
    ];
}

function fakeBlog(): void
{
    $image = imagecreatetruecolor(781, 1108);
    ob_start();
    imagejpeg($image);
    $jpeg = ob_get_clean();

    Http::fake([
        'bukugaleri.blogspot.com/feeds/posts/default/986341215997817318*' => Http::response(['entry' => blogEntry()]),
        'bukugaleri.blogspot.com/feeds/posts/default*' => Http::response(['feed' => ['entry' => [blogEntry()]]]),
        'blogger.googleusercontent.com/*' => Http::response($jpeg, 200, ['Content-Type' => 'image/jpeg']),
    ]);
}

beforeEach(function () {
    Storage::fake('public');
    config(['services.book_blog.url' => 'https://bukugaleri.blogspot.com']);
});

it('reads the title, author, synopsis and full-size cover from a blog post', function () {
    $komik = Category::query()->where('slug', 'komik')->first();

    $book = BlogBooks::parse(blogEntry());

    expect($book['title'])->toBe('Yeah, Dah Solat 2')
        ->and($book['author'])->toBe('Pakcik Bad')
        ->and($book['synopsis'])->toBe("“Nampaknya Fateh belum kenal kita,” kata Syaitan Kurus.\n\nJom ikut kisah Fateh.")
        ->and($book['isbn'])->toBe('978-629-7716-58-9')
        ->and($book['code'])->toBe('GB0892')
        ->and($book['pages'])->toBe(104)
        ->and($book['print_price'])->toBe('RM15.00')
        ->and($book['category_ids'])->toBe([$komik->id])
        ->and($book['cover_url'])->toBe('https://blogger.googleusercontent.com/img/b/R29v/AVvX/s0/yeah%20solat.jpg');
});

it('searches the blog by title', function () {
    fakeBlog();

    expect(BlogBooks::search('yeah solat'))->toBe(['986341215997817318' => 'YEAH, DAH SOLAT 2'])
        ->and(BlogBooks::search('y'))->toBe([]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'q=yeah+solat') || str_contains($request->url(), 'q=yeah%20solat'));
});

it('fills the new book form from the blog without saving it', function () {
    fakeBlog();
    $komik = Category::query()->where('slug', 'komik')->first();
    $this->actingAs(User::factory()->role(Role::Editor)->create());

    $page = Livewire::test(CreateBook::class)
        ->callAction('importFromBlog', data: ['post' => '986341215997817318'])
        ->assertHasNoActionErrors()
        ->assertSchemaStateSet([
            'title' => 'Yeah, Dah Solat 2',
            'author' => 'Pakcik Bad',
            'categories' => [$komik->id],
        ])
        ->assertNotified('Borang diisi dari blog');

    $cover = collect($page->get('data.cover_path'))->first();
    expect($cover)->toStartWith('covers/yeah-dah-solat-2-')
        ->and(Storage::disk('public')->exists($cover))->toBeTrue()
        ->and(Book::count())->toBe(0);
});

it('tells staff when the blog cannot be reached', function () {
    Http::fake(['*' => Http::response('', 500)]);
    $this->actingAs(User::factory()->role(Role::Editor)->create());

    Livewire::test(CreateBook::class)
        ->callAction('importFromBlog', data: ['post' => '986341215997817318'])
        ->assertNotified('Tidak dapat membaca blog')
        ->assertSchemaStateSet(['title' => null]);
});

it('creates several draft books at once and never imports a post twice', function () {
    fakeBlog();
    $komik = Category::query()->where('slug', 'komik')->first();
    $this->actingAs(User::factory()->role(Role::Editor)->create());
    BlogBooks::search('yeah');

    Livewire::test(ListBooks::class)
        ->callAction('importManyFromBlog', data: ['posts' => ['986341215997817318'], 'age_group' => '6-9'])
        ->assertHasNoActionErrors()
        ->assertNotified('Buku ditarik dari blog');

    $book = Book::query()->sole();
    expect($book->title)->toBe('Yeah, Dah Solat 2')
        ->and($book->blog_post_id)->toBe('986341215997817318')
        ->and($book->status)->toBe(BookStatus::Draft)
        ->and($book->age_group->value)->toBe('6-9')
        ->and($book->categories->pluck('id')->all())->toBe([$komik->id])
        ->and(Storage::disk('public')->exists($book->cover_path))->toBeTrue();

    // Imported posts drop out of the bulk search, and a second import is refused.
    Livewire::test(ListBooks::class)
        ->callAction('importManyFromBlog', data: ['posts' => ['986341215997817318'], 'age_group' => '6-9'])
        ->assertNotified('Tiada buku dicipta');
    expect(Book::count())->toBe(1);
});

it('marks posts that are already books in the single search and refuses to fill a duplicate', function () {
    fakeBlog();
    Book::factory()->create(['title' => 'Yeah, Dah Solat 2', 'blog_post_id' => '986341215997817318']);
    $this->actingAs(User::factory()->role(Role::Editor)->create());

    Livewire::test(CreateBook::class)
        ->callAction('importFromBlog', data: ['post' => '986341215997817318'])
        ->assertNotified('Buku ini sudah ada')
        ->assertSchemaStateSet(['title' => null]);
});
