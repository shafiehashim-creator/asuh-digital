<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Video;
use Illuminate\Support\Collection;

/**
 * The Mod Anak shelf (families and pupils) as rows by category, like a
 * streaming app: each row mixes the books and animated videos of one
 * category, a book's videos right after it. Small categories join a
 * "Lain-lain" row, and a small shelf stays one simple grid. A series
 * (two or more of its books on the shelf) comes first, as its own row in
 * reading order.
 */
class ChildShelf
{
    /** Below this many books and videos together, the shelf stays one grid. */
    public const MIN_ITEMS = 12;

    /** A category with fewer items than this joins the "Lain-lain" row. */
    public const MIN_ROW = 3;

    /**
     * @param  Collection<int, Book>  $books  with categories loaded
     * @param  Collection<int, array{video: Video, locked: ?string}>  $videos  with categories loaded
     * @return list<array{title: string, emoji: string, items: list<array<string, mixed>>}>|null null keeps the simple grid
     */
    public function rows(Collection $books, Collection $videos): ?array
    {
        if ($books->count() + $videos->count() < self::MIN_ITEMS) {
            return null;
        }

        // Each item goes on its first category's row only, so the shelf does not repeat itself.
        $rows = [];
        $add = function ($category, array $item) use (&$rows) {
            $key = $category?->id ?? 0;
            $rows[$key] ??= ['category' => $category, 'items' => []];
            $rows[$key]['items'][] = $item;
        };

        $videosByBook = $videos->filter(fn (array $item) => $item['video']->book_id)->groupBy(fn (array $item) => $item['video']->book_id);
        $placed = [];

        // A series with two or more books on the shelf gets its own row, in reading order.
        $seriesRows = [];
        $inSeries = [];
        foreach ($books->whereNotNull('book_series_id')->groupBy('book_series_id') as $members) {
            if ($members->count() < 2) {
                continue;
            }
            $items = [];
            foreach ($members->sortBy(fn (Book $b) => [$b->series_position ?? PHP_INT_MAX, $b->id]) as $book) {
                $inSeries[$book->id] = true;
                $items[] = ['type' => 'book', 'book' => $book];
                foreach ($videosByBook->get($book->id, []) as $item) {
                    $items[] = ['type' => 'video', ...$item];
                    $placed[$item['video']->id] = true;
                }
            }
            $series = $members->first()->series;
            $seriesRows[] = ['title' => $series?->localName() ?? __('Siri'), 'emoji' => '📚', 'items' => $items];
        }

        foreach ($books as $book) {
            if (isset($inSeries[$book->id])) {
                continue;
            }
            $category = $book->categories->first();
            $add($category, ['type' => 'book', 'book' => $book]);

            // The book's animation sits right after it, in the same row.
            foreach ($videosByBook->get($book->id, []) as $item) {
                $add($category, ['type' => 'video', ...$item]);
                $placed[$item['video']->id] = true;
            }
        }

        foreach ($videos as $item) {
            if (! isset($placed[$item['video']->id])) {
                $add($item['video']->categories->first(), ['type' => 'video', ...$item]);
            }
        }

        $named = collect($rows)->filter(fn (array $row) => $row['category'] && count($row['items']) >= self::MIN_ROW)
            ->sortBy(fn (array $row) => [$row['category']->sort_order, $row['category']->name]);
        $others = collect($rows)->diffKeys($named)->flatMap(fn (array $row) => $row['items'])->values()->all();

        if ($named->count() + count($seriesRows) < 2 && $others === []) {
            return null;
        }

        $result = [...$seriesRows, ...$named->map(fn (array $row) => [
            'title' => $row['category']->name,
            'emoji' => $row['category']->emoji(),
            'items' => $row['items'],
        ])->values()->all()];

        if ($others !== []) {
            $result[] = ['title' => 'Lain-lain', 'emoji' => '✨', 'items' => $others];
        }

        return count($result) > 1 ? $result : null;
    }
}
