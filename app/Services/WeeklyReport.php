<?php

namespace App\Services;

use App\Enums\Badge;
use App\Models\Child;
use App\Models\ChildBadge;
use App\Models\QuizAttempt;
use App\Models\ReadingSession;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * What each child read in a week, for the parent's weekly e-mail.
 */
class WeeklyReport
{
    /** The Monday-to-Sunday week before $now. */
    public static function lastWeek(?Carbon $now = null): array
    {
        $start = ($now ?? now())->copy()->subWeek()->startOfWeek(Carbon::MONDAY);

        return [$start, $start->copy()->endOfWeek(Carbon::SUNDAY)];
    }

    /**
     * One entry per child who read in the week; children who did not read are left out.
     *
     * @return list<array{child: Child, minutes: int, days: int, books: list<string>, quizzes: list<string>, badges: list<Badge>}>
     */
    public function forParent(User $parent, Carbon $from, Carbon $to): array
    {
        $report = [];

        foreach ($parent->children()->get() as $child) {
            $sessions = ReadingSession::query()
                ->where('child_id', $child->id)
                ->whereBetween('read_on', [$from->toDateString(), $to->toDateString()])
                ->with('book')
                ->get();

            if ($sessions->sum('minutes') === 0) {
                continue;
            }

            $report[] = [
                'child' => $child,
                'minutes' => (int) $sessions->sum('minutes'),
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

        return $report;
    }
}
