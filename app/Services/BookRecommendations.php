<?php

namespace App\Services;

use App\Enums\AgeGroup;
use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * "Anda mungkin suka juga" under every book page, to lead readers (and
 * buyers) on to the next book: books sharing the most categories first,
 * then ones for the same age, then the newest.
 */
class BookRecommendations
{
    public const LIMIT = 8;

    /** @return Collection<int, Book> */
    public function for(Book $book, ?User $user = null): Collection
    {
        $categoryIds = $book->categories->modelKeys();
        $ages = $book->age_group === AgeGroup::Both
            ? array_map(fn (AgeGroup $age) => $age->value, AgeGroup::cases())
            : [$book->age_group->value, AgeGroup::Both->value];

        $books = Book::query()
            ->published()
            ->whereKeyNot($book->id)
            ->with('categories')
            ->withCount(['categories as shared_categories' => fn ($q) => $q->whereIn('categories.id', $categoryIds)])
            ->orderByDesc('shared_categories')
            ->orderByRaw('CASE WHEN age_group IN ('.implode(',', array_fill(0, count($ages), '?')).') THEN 0 ELSE 1 END', $ages)
            ->latest('published_at')
            ->limit(self::LIMIT)
            ->get();

        // Whether each one is already open to this reader (pass or purchase), for the card label.
        $books->each(fn (Book $other) => $other->setAttribute('readable', (bool) $user?->canReadBook($other)));

        return $books;
    }
}
