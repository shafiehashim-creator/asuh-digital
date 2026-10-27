<?php

use App\Enums\AgeGroup;
use App\Models\Book;
use App\Models\Category;
use App\Models\Child;
use App\Models\Order;
use App\Models\Pass;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use App\Services\ChildShelf;

beforeEach(function () {
    Setting::set('catalog_open', true);
});

function shelfChild(): Child
{
    $parent = User::factory()->create();
    $order = Order::factory()->paid()->create(['user_id' => $parent->id]);
    Pass::query()->create([
        'user_id' => $parent->id, 'plan_id' => $order->plan_id, 'order_id' => $order->id,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(),
    ]);

    return Child::factory()->for($parent)->create(['age_group' => AgeGroup::SixToNine]);
}

function shelfCategory(string $name, string $slug, int $order): Category
{
    return Category::updateOrCreate(['slug' => $slug], ['name' => $name, 'sort_order' => $order]);
}

function shelfBooks(Category $category, int $count, string $prefix): void
{
    Book::factory()->published()->count($count)->sequence(fn ($s) => ['title' => "{$prefix} {$s->index}", 'age_group' => AgeGroup::SixToNine])
        ->create()->each(fn (Book $book) => $book->categories()->sync([$category->id]));
}

it('keeps a small shelf as one grid', function () {
    $category = shelfCategory('Akhlak', 'akhlak', 1);
    shelfBooks($category, 5, 'Buku');

    $books = Book::with('categories')->get();

    expect(app(ChildShelf::class)->rows($books, collect()))->toBeNull();
});

it('puts a big shelf in category rows, in the admin order, with small categories under Lain-lain', function () {
    $akhlak = shelfCategory('Akhlak', 'akhlak', 2);
    $nabi = shelfCategory('Kisah nabi', 'kisah-nabi', 1);
    $doa = shelfCategory('Doa harian', 'doa-harian', 3);
    shelfBooks($akhlak, 5, 'Akhlak');
    shelfBooks($nabi, 6, 'Nabi');
    shelfBooks($doa, 2, 'Doa');

    $rows = app(ChildShelf::class)->rows(Book::with('categories')->get(), collect());

    expect(array_column($rows, 'title'))->toBe(['Kisah nabi', 'Akhlak', 'Lain-lain'])
        ->and($rows[0]['emoji'])->toBe('🌙')
        ->and(count($rows[0]['items']))->toBe(6)
        ->and(count($rows[2]['items']))->toBe(2);
});

it('places a book\'s animation right after the book', function () {
    $akhlak = shelfCategory('Akhlak', 'akhlak', 1);
    $nabi = shelfCategory('Kisah nabi', 'kisah-nabi', 2);
    shelfBooks($akhlak, 6, 'Akhlak');
    shelfBooks($nabi, 6, 'Nabi');
    $book = Book::where('title', 'Akhlak 2')->first();
    $video = Video::factory()->published()->create(['book_id' => $book->id, 'age_group' => AgeGroup::SixToNine]);

    $books = Book::with('categories')->latest('id')->get();
    $rows = app(ChildShelf::class)->rows($books, collect([['video' => $video->load('categories'), 'locked' => null]]));

    $items = collect($rows[0]['items']);
    $bookAt = $items->search(fn ($item) => $item['type'] === 'book' && $item['book']->is($book));
    expect($items[$bookAt + 1]['type'])->toBe('video')
        ->and($items[$bookAt + 1]['video']->is($video))->toBeTrue();
});

it('shows the category rows in Mod Anak', function () {
    $akhlak = shelfCategory('Akhlak', 'akhlak', 1);
    $nabi = shelfCategory('Kisah nabi', 'kisah-nabi', 2);
    shelfBooks($akhlak, 6, 'Akhlak');
    shelfBooks($nabi, 6, 'Nabi');
    $child = shelfChild();

    $this->actingAs($child->user)->post("/akaun/anak/{$child->id}/mod-anak");

    $this->get('/mod-anak')->assertOk()
        ->assertSeeInOrder(['💛', 'Akhlak', '🌙', 'Kisah nabi'])
        ->assertSee('Akhlak 0')->assertSee('Nabi 0')
        ->assertDontSee('Semua buku');
});
