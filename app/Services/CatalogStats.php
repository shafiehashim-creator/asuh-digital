<?php

namespace App\Services;

use App\Enums\BookEdition;
use App\Enums\OrderStatus;
use App\Models\Book;
use App\Models\BookFile;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Numbers and shelves for the landing page and the pass sales page. Small
 * counts are left out by the views (a "3 keluarga" badge discourages).
 */
class CatalogStats
{
    /** Below this, family counts are not shown. */
    public const MIN_FAMILIES = 20;

    /** Below this, a book's reader count is not shown. */
    public const MIN_READERS = 5;

    /** @return array{books: int, pages: int, categories: int, families: int, single_total_sen: int} */
    public function summary(): array
    {
        return Cache::remember('catalog-stats', now()->addMinutes(10), function () {
            $published = Book::query()->published();

            return [
                'books' => (clone $published)->count(),
                'pages' => (int) BookFile::query()
                    ->where('edition', BookEdition::Original)
                    ->whereIn('book_id', (clone $published)->select('id'))
                    ->sum('page_count'),
                'categories' => (clone $published)->join('book_category', 'book_category.book_id', '=', 'books.id')
                    ->distinct()->count('book_category.category_id'),
                'families' => Order::query()->where('status', OrderStatus::Paid)->distinct()->count('user_id'),
                // What buying every priced book one by one would cost.
                'single_total_sen' => (int) (clone $published)->sum('single_price_sen'),
            ];
        });
    }

    /**
     * Most-read books; until anyone has read, the newest stand in (ranked = false).
     *
     * @return array{books: Collection<int, Book>, ranked: bool}
     */
    public function popular(int $limit = 10): array
    {
        $books = Book::query()->published()
            ->withCount('readers')
            ->with('categories')
            ->orderByDesc('readers_count')
            ->latest('published_at')
            ->limit($limit)
            ->get();

        return ['books' => $books, 'ranked' => $books->sum('readers_count') > 0];
    }

    /** @return Collection<int, Book> */
    public function newest(int $limit = 8): Collection
    {
        return Book::query()->published()->with('categories')->latest('published_at')->limit($limit)->get();
    }
}
