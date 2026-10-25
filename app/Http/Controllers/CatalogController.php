<?php

namespace App\Http\Controllers;

use App\Enums\AgeGroup;
use App\Models\Book;
use App\Models\Category;
use App\Services\BookRecommendations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        $category = $request->filled('kategori')
            ? Category::query()->where('slug', $request->string('kategori'))->first()
            : null;
        $age = AgeGroup::tryFrom((string) $request->query('umur'));
        $search = trim((string) $request->query('cari'));

        $books = Book::query()
            ->published()
            ->with('categories')
            ->when($category, fn (Builder $q) => $q->whereRelation('categories', 'categories.id', $category->id))
            ->when($age && $age !== AgeGroup::Both, fn (Builder $q) => $q->forAge($age))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('title', 'like', "%{$search}%")
                ->orWhere('author', 'like', "%{$search}%")))
            ->latest('published_at')
            ->paginate(24)
            ->withQueryString();

        return view('catalog.index', [
            'books' => $books,
            'categories' => Category::query()->orderBy('sort_order')->get(),
            'activeCategory' => $category,
            'activeAge' => $age,
            'search' => $search,
        ]);
    }

    public function show(Request $request, Book $book, BookRecommendations $recommendations): View
    {
        $user = $request->user();

        // Unpublished books exist only for staff previews.
        abort_unless($book->isPublished() || $user?->isStaff(), 404);

        $book->load(['categories', 'files']);

        return view('catalog.show', [
            'book' => $book,
            'canRead' => $user?->canReadBook($book) ?? false,
            'recommended' => $recommendations->for($book, $user),
            'hasPass' => $user && ($user->isStaff() || $user->passes()->active()->exists()),
        ]);
    }
}
