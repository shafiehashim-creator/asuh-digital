<?php

namespace App\Http\Controllers;

use App\Enums\AgeGroup;
use App\Models\Book;
use App\Models\Category;
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
            ->with('category')
            ->when($category, fn (Builder $q) => $q->where('category_id', $category->id))
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

    public function show(Request $request, Book $book): View
    {
        $user = $request->user();

        // Unpublished books exist only for staff previews.
        abort_unless($book->isPublished() || $user?->isStaff(), 404);

        $book->load(['category', 'files']);

        return view('catalog.show', [
            'book' => $book,
            'canRead' => $user?->canReadBook($book) ?? false,
            'related' => Book::query()->published()
                ->where('category_id', $book->category_id)
                ->whereKeyNot($book->id)
                ->latest('published_at')->limit(4)->get(),
        ]);
    }
}
