<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Child;
use App\Models\ReadingSession;
use Illuminate\Database\Eloquent\Collection;

/**
 * Records reading in Mod Anak. The reader sends a heartbeat each minute while
 * the page is on screen and the child is turning pages; each one adds at most
 * one minute, so leaving a book open (or calling the endpoint in a loop) does
 * not pile up minutes.
 */
class ReadingLog
{
    /** Heartbeats closer together than this add no minute. */
    public const MIN_TICK_SECONDS = 50;

    /** Upper bound per child, book and day. */
    public const MAX_MINUTES_PER_DAY = 240;

    public function record(Child $child, Book $book, int $page, bool $tick): ReadingSession
    {
        $session = ReadingSession::query()->firstOrCreate(
            ['child_id' => $child->id, 'book_id' => $book->id, 'read_on' => today()],
        );

        $session->last_page = $page;

        $due = $session->last_tick_at === null
            || $session->last_tick_at->lte(now()->subSeconds(self::MIN_TICK_SECONDS));

        if ($tick && $due && $session->minutes < self::MAX_MINUTES_PER_DAY) {
            $session->minutes++;
            $session->last_tick_at = now();
        }

        $session->save();

        return $session;
    }

    /** Where the child stopped in this book, on any day. */
    public function lastPage(Child $child, Book $book): ?int
    {
        return ReadingSession::query()
            ->where('child_id', $child->id)->where('book_id', $book->id)
            ->latest('updated_at')->value('last_page');
    }

    /**
     * Books the child read lately, newest first, for "Sambung membaca".
     *
     * @return Collection<int, ReadingSession>
     */
    public function recent(Child $child, int $limit = 6): Collection
    {
        return ReadingSession::query()
            ->where('child_id', $child->id)
            ->with('book')
            ->latest('updated_at')
            ->get()
            ->unique('book_id')
            ->take($limit)
            ->values();
    }
}
