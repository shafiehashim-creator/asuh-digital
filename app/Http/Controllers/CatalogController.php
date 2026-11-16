<?php

namespace App\Http\Controllers;

use App\Enums\AgeGroup;
use App\Models\Book;
use App\Models\Category;
use App\Services\BookRecommendations;
use App\Services\CatalogStats;
use App\Services\PassOffer;
use App\Services\VideoShowcase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CatalogController extends Controller
{
    private const PER_PAGE = 24;

    /**
     * Books of the same series become one card at the place of the series'
     * newest book. A series with one matching book stays a book card.
     *
     * @param  Collection<int, Book>  $books  newest first
     */
    private function groupSeries(Collection $books, Request $request): LengthAwarePaginator
    {
        $items = [];
        $done = [];
        foreach ($books as $book) {
            $id = $book->book_series_id;
            if ($id === null) {
                $items[] = ['book' => $book];

                continue;
            }
            if (isset($done[$id])) {
                continue;
            }
            $done[$id] = true;

            $members = $books->where('book_series_id', $id);
            $items[] = $members->count() < 2
                ? ['book' => $book]
                : ['series' => $book->series, 'books' => $members->sortBy(fn (Book $b) => [$b->series_position ?? PHP_INT_MAX, $b->id])->values()];
        }

        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            array_slice($items, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            count($items), self::PER_PAGE, $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }

    public function index(Request $request): View
    {
        $category = $request->filled('kategori')
            ? Category::query()->where('slug', $request->string('kategori'))->first()
            : null;
        $age = AgeGroup::tryFrom((string) $request->query('umur'));
        $search = trim((string) $request->query('cari'));

        $query = Book::query()
            ->published()
            ->with(['categories', 'series'])
            ->when($category, fn (Builder $q) => $q->whereRelation('categories', 'categories.id', $category->id))
            ->when($age && $age !== AgeGroup::Both, fn (Builder $q) => $q->forAge($age))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('title', 'like', "%{$search}%")
                ->orWhere('author', 'like', "%{$search}%")))
            ->latest('published_at');

        // A search lists every matching book; otherwise a series is one card.
        $items = $search !== ''
            ? $query->paginate(self::PER_PAGE)->withQueryString()->through(fn (Book $book) => ['book' => $book])
            : $this->groupSeries($query->get(), $request);

        return view('catalog.index', [
            'items' => $items,
            'categories' => Category::query()->orderBy('sort_order')->get(),
            'activeCategory' => $category,
            'activeAge' => $age,
            'search' => $search,
        ]);
    }

    public function show(Request $request, Book $book, BookRecommendations $recommendations, PassOffer $offer, CatalogStats $stats, VideoShowcase $showcase): View
    {
        $user = $request->user();

        // Unpublished books exist only for staff previews.
        abort_unless($book->isPublished() || $user?->isStaff(), 404);

        $book->load(['categories', 'files', 'series'])->loadCount('readers');

        // The other books of its series, in reading order (drafts for staff only).
        $seriesBooks = $book->series
            ? ($user?->isStaff() ? $book->series->books() : $book->series->publishedBooks())->get()
            : collect();

        return view('catalog.show', [
            'book' => $book,
            'seriesBooks' => $seriesBooks->count() > 1 ? $seriesBooks : collect(),
            'canRead' => $user?->canReadBook($book) ?? false,
            'recommended' => $recommendations->for($book, $user),
            'hasPass' => $user && ($user->isStaff() || $user->passes()->active()->exists()),
            'offer' => $offer,
            'bookCount' => $stats->summary()['books'],
            'previewPages' => $book->previewPageCount(),
            // Phase 2: the book's animated version, once the videos open.
            'videos' => $showcase->isOpenFor($user) ? $showcase->forBook($book) : collect(),
            'showcase' => $showcase,
        ]);
    }
}
