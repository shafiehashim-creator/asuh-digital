<?php

namespace App\Services;

use App\Enums\Badge;
use App\Models\Book;
use App\Models\Child;
use App\Models\ChildBadge;
use App\Models\QuizAttempt;
use App\Models\ReadingSession;
use App\Models\VideoView;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What a child read, watched and earned between two dates: the parent's
 * account pages and the weekly e-mail (WeeklyReport) count it the same way.
 */
class FamilyProgress
{
    public function __construct(private ReadingLog $log, private ChildMode $childMode) {}

    /** Monday of this week until now. */
    public static function thisWeek(?Carbon $now = null): array
    {
        $now ??= now();

        return [$now->copy()->startOfWeek(Carbon::MONDAY), $now->copy()];
    }

    /**
     * @return array{child: Child, minutes: int, video_minutes: int, days: int, books: list<string>, quizzes: list<string>, badges: list<Badge>}
     */
    public function between(Child $child, Carbon $from, Carbon $to): array
    {
        $sessions = ReadingSession::query()
            ->where('child_id', $child->id)
            // whereDate: SQLite keeps date columns with a time part.
            ->whereDate('read_on', '>=', $from)->whereDate('read_on', '<=', $to)
            ->with('book')
            ->get();

        // Phase 2: animated videos watched, shown apart from reading.
        $watchedSeconds = (int) VideoView::query()
            ->where('child_id', $child->id)
            ->whereDate('watched_on', '>=', $from)->whereDate('watched_on', '<=', $to)
            ->sum('seconds');

        return [
            'child' => $child,
            'minutes' => (int) $sessions->sum('minutes'),
            'video_minutes' => intdiv($watchedSeconds + 59, 60),
            'days' => $sessions->where('minutes', '>', 0)->map(fn ($s) => $s->read_on->toDateString())->unique()->count(),
            'books' => $sessions->pluck('book.title')->filter()->unique()->values()->all(),
            'quizzes' => QuizAttempt::query()
                ->where('child_id', $child->id)->where('passed', true)
                ->whereBetween('created_at', [$from, $to])
                ->with('quiz.book')->get()
                ->pluck('quiz.book.title')->filter()->unique()->values()->all(),
            'badges' => ChildBadge::query()
                ->where('child_id', $child->id)
                ->whereBetween('awarded_at', [$from, $to])
                ->get()->pluck('badge')->all(),
        ];
    }

    /**
     * Books the children were reading lately, newest first, each with how far
     * the child got (null when the page count is unknown).
     *
     * @param  Collection<int, Child>  $children
     * @return Collection<int, array{child: Child, book: Book, page: int, percent: int|null}>
     */
    public function continueReading(Collection $children, int $limit = 4): Collection
    {
        return $children
            ->flatMap(fn (Child $child) => $this->log->recent($child, $limit)
                ->filter(fn ($session) => $session->book && $this->childMode->allows($child, $session->book))
                ->map(fn ($session) => [
                    'child' => $child,
                    'book' => $session->book,
                    'page' => $session->last_page,
                    'percent' => $this->percent($session->book, $session->last_page),
                    'at' => $session->updated_at,
                ]))
            ->sortByDesc('at')
            ->take($limit)
            ->map(fn (array $item) => collect($item)->except('at')->all())
            ->values();
    }

    private function percent(Book $book, int $page): ?int
    {
        $file = $book->readableFile();
        $pages = $file?->page_count ?: $file?->page_images;

        return $pages ? (int) min(100, round($page / $pages * 100)) : null;
    }
}
